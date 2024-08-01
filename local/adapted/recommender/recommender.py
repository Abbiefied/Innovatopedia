import os
import json
import pandas as pd
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from surprise import SVD, Dataset, Reader
from sqlalchemy import create_engine
import pickle
import logging
from datetime import datetime
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import normalize
import sys
import re  # Import the re module

# Set up logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

class MoodleRecommender:
    def __init__(self, engine):
        self.engine = engine
        self.cf_model = SVD()
        self.content_vectorizer = TfidfVectorizer(stop_words='english')
        self.content_vectors = None
        self.user_preferences = {}
        self.hybrid_model = RandomForestRegressor(n_estimators=100, random_state=42)
        self.content_vector_size = None
        self.course_content = None

    def load_data(self):
        logging.info("Starting to load data")
        try:
            self.users = pd.read_sql("SELECT id, username FROM mdl_user", self.engine)
            logging.info(f"Loaded {len(self.users)} users")
            
            self.courses = pd.read_sql("SELECT id, fullname, summary FROM mdl_course", self.engine)
            logging.info(f"Loaded {len(self.courses)} courses")
            
            self.course_content = pd.read_sql("""
                SELECT r.id, r.course, r.name, r.intro, 'resource' as type, cm.id as cmid
                FROM mdl_resource r
                JOIN mdl_course_modules cm ON cm.instance = r.id AND cm.module = (SELECT id FROM mdl_modules WHERE name = 'resource')
                UNION ALL
                SELECT p.id, p.course, p.name, p.intro, 'page' as type, cm.id as cmid
                FROM mdl_page p
                JOIN mdl_course_modules cm ON cm.instance = p.id AND cm.module = (SELECT id FROM mdl_modules WHERE name = 'page')
            """, self.engine)
            logging.info(f"Loaded {len(self.course_content)} course content items")
            
            self.user_content_interactions = pd.read_sql("""
                SELECT l.userid, l.courseid, l.objectid as cmid, COUNT(*) as interaction_count, 
                    CASE 
                        WHEN l.component = 'mod_resource' THEN 'resource'
                        WHEN l.component = 'mod_page' THEN 'page'
                        ELSE 'unknown'
                    END as type,
                    MAX(l.timecreated) as last_interaction
                FROM mdl_logstore_standard_log l
                WHERE l.action = 'viewed' 
                AND l.component IN ('mod_resource', 'mod_page')
                AND l.contextlevel = 70  -- Module context level
                GROUP BY l.userid, l.courseid, l.objectid, 
                        CASE 
                            WHEN l.component = 'mod_resource' THEN 'resource'
                            WHEN l.component = 'mod_page' THEN 'page'
                            ELSE 'unknown'
                        END
            """, self.engine)
            logging.info(f"Loaded {len(self.user_content_interactions)} user interactions")
        except Exception as e:
            logging.error(f"Error loading data: {str(e)}")
            raise

    def preprocess_data(self):
        # Prepare data for collaborative filtering
        reader = Reader(rating_scale=(0, 100))
        self.cf_data = Dataset.load_from_df(self.user_content_interactions[['userid', 'cmid', 'interaction_count']], reader)

        # Prepare data for content-based filtering
        self.course_content['content'] = self.course_content['name'] + ' ' + self.course_content['intro'] + ' ' + self.course_content['type']
        self.content_vectors = self.content_vectorizer.fit_transform(self.course_content['content'])
        self.content_vector_size = self.content_vectors.shape[1]

        # Normalize content vectors
        self.content_vectors = normalize(self.content_vectors.toarray(), norm='l2', axis=1)

        # Create a dictionary mapping content ID to its vector
        self.content_vector_dict = {id: vec for id, vec in zip(self.course_content['id'], self.content_vectors)}

        # Prepare data for hybrid model
        self.hybrid_data = self.user_content_interactions.copy()
        self.hybrid_data['cf_score'] = 0.0
        self.hybrid_data['cb_score'] = 0.0

    def get_user_profile(self, user_id):
        user_interactions = self.user_content_interactions[self.user_content_interactions['userid'] == user_id]
        user_content_ids = user_interactions['cmid'].unique()
        
        if self.content_vector_size is None:
            logging.warning("content_vector_size is None. Initializing with default size.")
            self.content_vector_size = len(next(iter(self.content_vector_dict.values())))  # Get size from first vector
        
        if len(user_content_ids) == 0:
            return np.zeros((1, self.content_vector_size))
        
        user_vectors = [self.content_vector_dict.get(id, np.zeros(self.content_vector_size)) for id in user_content_ids]
        return np.mean(user_vectors, axis=0).reshape(1, -1)

    def train_cb_model(self):
        for _, row in self.hybrid_data.iterrows():
            user_profile = self.get_user_profile(row['userid'])
            content_vector = self.content_vector_dict.get(row['cmid'], np.zeros((1, self.content_vector_size)))
            self.hybrid_data.at[_, 'cb_score'] = cosine_similarity(user_profile, content_vector.reshape(1, -1))[0][0]
    
    def train_cf_model(self):
        # Train the collaborative filtering model
        trainset = self.cf_data.build_full_trainset()
        self.cf_model.fit(trainset)
        
        # Calculate CF scores for hybrid model
        for _, row in self.hybrid_data.iterrows():
            cf_score = self.cf_model.predict(row['userid'], row['cmid']).est
            self.hybrid_data.at[_, 'cf_score'] = float(cf_score)
            
    def train_hybrid_model(self):
        X = self.hybrid_data[['cf_score', 'cb_score']]
        y = self.hybrid_data['interaction_count']
        self.hybrid_model.fit(X, y)

    def get_content_based_recommendations(self, user_id, n=10):
        # Get content-based recommendations
        user_interactions = self.user_content_interactions[self.user_content_interactions['userid'] == user_id]
        user_content = self.course_content[self.course_content['id'].isin(user_interactions['cmid'])]
        if user_content.empty:
            return pd.DataFrame()  # Return empty DataFrame if user has no interactions
        user_profile = self.content_vectors[user_content.index].mean(axis=0)
        user_profile = np.asarray(user_profile).flatten() 
        content_scores = cosine_similarity(user_profile.reshape(1, -1), self.content_vectors)[0]
        top_indices = content_scores.argsort()[-n:][::-1]
        return self.course_content.iloc[top_indices]
    
    def get_collaborative_recommendations(self, user_id, n=10):
        # Get collaborative filtering recommendations
        all_content = self.course_content['id'].unique()
        cf_scores = [self.cf_model.predict(user_id, content_id).est for content_id in all_content]
        top_indices = np.array(cf_scores).argsort()[-n:][::-1]
        return self.course_content.iloc[top_indices]

    def get_user_preference(self, user_id):
        if user_id not in self.user_preferences:
            user_interactions = self.user_content_interactions[self.user_content_interactions['userid'] == user_id]
            type_counts = user_interactions.groupby('type')['interaction_count'].sum()
            total_interactions = type_counts.sum()
            type_weights = (type_counts / total_interactions).to_dict()
            self.user_preferences[user_id] = type_weights
        return self.user_preferences[user_id]

    def get_recommendations_by_type(self, user_id, content_type, n=10):
        hybrid_recs = self.get_hybrid_recommendations(user_id, n=n*2)
        type_recs = hybrid_recs[hybrid_recs['type'] == content_type]
        
        if len(type_recs) < n:
            other_recs = hybrid_recs[hybrid_recs['type'] != content_type].head(n - len(type_recs))
            type_recs = pd.concat([type_recs, other_recs])
        
        return type_recs.head(n)

    def get_time_decay_factor(self, last_interaction):
        current_time = datetime.now().timestamp()
        days_since_interaction = (current_time - last_interaction) / (24 * 3600)
        return np.exp(-0.1 * days_since_interaction)

    def get_hybrid_recommendations(self, user_id, course_id, n=10):
        logging.debug(f"Getting recommendations for user {user_id} in context of course {course_id}")
        if self.course_content is None or self.course_content.empty:
            logging.warning("Course content is empty")
            return self.get_default_recommendations(n)

        all_content = self.course_content['id'].unique()
        user_profile = self.get_user_profile(user_id)
        user_preferences = self.get_user_preference(user_id)
        
        # Get user's interactions in the current course
        course_interactions = self.user_content_interactions[
            (self.user_content_interactions['userid'] == user_id) & 
            (self.user_content_interactions['courseid'] == course_id)
        ]
        
        recommendations = []
        for content_id in all_content:
            cf_score = self.cf_model.predict(user_id, content_id).est
            content_vector = self.content_vector_dict.get(content_id, np.zeros((1, self.content_vector_size)))
            cb_score = cosine_similarity(user_profile, content_vector.reshape(1, -1))[0][0]
            
            # Create a DataFrame for prediction
            pred_data = pd.DataFrame({'cf_score': [cf_score], 'cb_score': [cb_score]})
            hybrid_score = self.hybrid_model.predict(pred_data)[0]
            
            content_info = self.course_content[self.course_content['id'] == content_id].iloc[0]
            content_type = content_info['type']
            
            # Apply content type weight
            type_weight = user_preferences.get(content_type, 0.1)  # Default weight of 0.1 if type not in preferences
            hybrid_score *= (1 + type_weight)  # Boost score based on content type preference
            
            # Consider the course context
            if content_info['course'] == course_id:
                hybrid_score *= 1.2  # Boost score for content in the current course
            
            # Consider user's interaction patterns
            if not course_interactions.empty:
                type_interactions = course_interactions[course_interactions['type'] == content_type]
                if not type_interactions.empty:
                    interaction_boost = np.log1p(type_interactions['interaction_count'].sum()) / 10
                    hybrid_score *= (1 + interaction_boost)
            
            last_interaction = self.user_content_interactions[
                (self.user_content_interactions['userid'] == user_id) & 
                (self.user_content_interactions['cmid'] == content_id)
            ]['last_interaction'].max()
            
            if pd.notnull(last_interaction):
                time_decay = self.get_time_decay_factor(last_interaction)
            else:
                time_decay = 1  # No decay for content never interacted with
            
            final_score = hybrid_score * time_decay
            
            recommendations.append({
                'content_id': content_id,
                'cmid': content_info['cmid'],
                'score': final_score,
                'type': content_type,
                'title': content_info['name'],
                'description': content_info['intro'][:100] + '...',  # Truncate description
                'course_id': content_info['course']
            })
        
        recommendations.sort(key=lambda x: x['score'], reverse=True)
        top_recommendations = recommendations[:n]
        
        logging.debug(f"Generated {len(recommendations)} recommendations considering course {course_id} context")
    
        return pd.DataFrame(top_recommendations)
    
    def get_default_recommendations(self, n=10):
        logging.warning("Returning default recommendations")
        return pd.DataFrame([
            {'content_id': 1, 'score': 1.0, 'type': 'resource', 'title': 'Default Course 1', 'description': 'This is a default recommendation'},
            {'content_id': 2, 'score': 0.9, 'type': 'page', 'title': 'Default Course 2', 'description': 'This is another default recommendation'},
            {'content_id': 3, 'score': 0.8, 'type': 'resource', 'title': 'Default Course 3', 'description': 'This is a third default recommendation'}
        ])


    def train_models(self):
        self.train_cf_model()
        self.train_cb_model()
        self.train_hybrid_model()

    def save_model(self, filename):
        with open(filename, 'wb') as f:
            pickle.dump({
                'cf_model': self.cf_model,
                'content_vectorizer': self.content_vectorizer,
                'content_vectors': self.content_vectors,
                'user_preferences': self.user_preferences,
                'hybrid_model': self.hybrid_model,
                'course_content': self.course_content, 
                'content_vector_dict': self.content_vector_dict,  
                'user_content_interactions': self.user_content_interactions
            }, f)

    def load_model(self, filename):
        with open(filename, 'rb') as f:
            data = pickle.load(f)
            self.cf_model = data['cf_model']
            self.content_vectorizer = data['content_vectorizer']
            self.content_vectors = data['content_vectors']
            self.user_preferences = data['user_preferences']
            self.hybrid_model = data['hybrid_model']
            self.course_content = data['course_content']
            self.content_vector_dict = data['content_vector_dict']
            self.user_content_interactions = data['user_content_interactions']
            
            # Set content_vector_size based on the loaded data
            if self.content_vectors is not None:
                self.content_vector_size = self.content_vectors.shape[1]
            elif self.content_vector_dict:
                self.content_vector_size = len(next(iter(self.content_vector_dict.values())))
            else:
                logging.warning("Unable to determine content_vector_size from loaded data")
                self.content_vector_size = None

        logging.info(f"Loaded course content with {len(self.course_content)} items")
        logging.info(f"Loaded user interactions with {len(self.user_content_interactions)} items")
        logging.info(f"Content vector size: {self.content_vector_size}")

def get_db_connection():
    # Read Moodle config.php
    with open('/var/www/html/config.php', 'r') as file:
        config_content = file.read()
    
    # Extract DB connection details
    dbuser = re.search(r"\$CFG->dbuser\s*=\s*'([^']+)';", config_content).group(1)
    dbpass = re.search(r"\$CFG->dbpass\s*=\s*'([^']+)';", config_content).group(1)
    dbhost = re.search(r"\$CFG->dbhost\s*=\s*'([^']+)';", config_content).group(1)
    dbname = re.search(r"\$CFG->dbname\s*=\s*'([^']+)';", config_content).group(1)

    return create_engine(f"postgresql://{dbuser}:{dbpass}@{dbhost}/{dbname}")

if __name__ == "__main__":
    engine = get_db_connection()
    recommender = MoodleRecommender(engine)
    
    if len(sys.argv) > 1 and sys.argv[1] == "update":
        logging.info("Starting model update")
        recommender.load_data()
        recommender.preprocess_data()
        recommender.train_models()
        recommender.save_model('moodle_recommender_model.pkl')
        logging.info(f"Model updated and saved successfully. Course content size: {len(recommender.course_content)}")
        print(f"Model file size: {os.path.getsize('moodle_recommender_model.pkl')} bytes")
        print(json.dumps({"status": "success", "message": "Model updated successfully"}))
    else:
        if len(sys.argv) < 3:
            print(json.dumps({"status": "error", "message": "User ID and Course ID not provided"}))
            sys.exit(1)
        
        user_id = int(sys.argv[1])
        course_id = int(sys.argv[2])
        
        logging.info(f"Generating recommendations for user {user_id} in context of course {course_id}")
        try:
            recommender.load_model('moodle_recommender_model.pkl')
            logging.info(f"Model loaded successfully. Course content size: {len(recommender.course_content)}")
        except Exception as e:
            logging.error(f"Error loading model: {str(e)}")
            print(json.dumps({"status": "error", "message": "Error loading model"}))
            sys.exit(1)
        
        if recommender.course_content is None or recommender.course_content.empty:
            logging.error("Course content is empty after loading the model")
        
        try:
            recommendations = recommender.get_hybrid_recommendations(user_id, course_id)
            logging.info(f"Generated {len(recommendations)} recommendations considering course {course_id} context")
            print(json.dumps(recommendations.to_dict('records')))
        except Exception as e:
            logging.error(f"Error generating recommendations: {str(e)}")
            print(json.dumps({"status": "error", "message": "Error generating recommendations"}))