from flask import Flask, jsonify, request
from recommender import MoodleRecommender, get_db_connection
import os

app = Flask(__name__)

import logging
logging.basicConfig(level=logging.DEBUG)

@app.route('/recommendations/<int:user_id>/<int:course_id>')
def get_recommendations(user_id, course_id):
    try:
        app.logger.debug(f"Received request for user_id: {user_id}, course_id: {course_id}")
        engine = get_db_connection()
        recommender = MoodleRecommender(engine)
        app.logger.debug("Loading model")
        recommender.load_model('moodle_recommender_model.pkl')
        app.logger.debug("Getting recommendations")
        recommendations = recommender.get_hybrid_recommendations(user_id, course_id)
        app.logger.debug(f"Got recommendations: {recommendations}")
        return jsonify(recommendations.to_dict('records'))
    except Exception as e:
        app.logger.error(f"Error generating recommendations for user {user_id} in course {course_id}: {str(e)}")
        return jsonify({"error": "An error occurred while generating recommendations"}), 500
    
@app.route('/update_model')
def update_model():
    engine = get_db_connection()
    recommender = MoodleRecommender(engine)
    recommender.load_data()
    recommender.preprocess_data()
    recommender.train_models()
    recommender.save_model('moodle_recommender_model.pkl')
    return jsonify({"status": "success", "message": "Model updated successfully"})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)