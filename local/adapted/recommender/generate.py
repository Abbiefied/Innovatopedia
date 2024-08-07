import random
from datetime import datetime, timedelta
from sqlalchemy import create_engine, text
import pandas as pd
import faker
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Database configuration
# DB_NAME = os.getenv('DB_NAME', 'DB_NAME')
# DB_USER = os.getenv('DB_USER', 'DB_USER')
# DB_PASSWORD = os.getenv('DB_PASSWORD', 'DB_PASSWORD')
# DB_HOST = os.getenv('DB_HOST', 'DB_HOST')
# DB_PORT = os.getenv('DB_PORT', '5432')

# Create a connection to the database
engine = create_engine(f'postgresql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}')

# Create a Faker instance for generating realistic-looking data
fake = faker.Faker()

def create_tables():
    with engine.connect() as connection:
        connection.execute(text("""
        CREATE TABLE IF NOT EXISTS mdl_user (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS mdl_course (
            id SERIAL PRIMARY KEY,
            fullname VARCHAR(254) NOT NULL,
            summary TEXT
        );

        CREATE TABLE IF NOT EXISTS mdl_resource (
            id SERIAL PRIMARY KEY,
            course INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            intro TEXT,
            mimetype VARCHAR(100) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS mdl_page (
            id SERIAL PRIMARY KEY,
            course INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            intro TEXT
        );

        CREATE TABLE IF NOT EXISTS mdl_logstore_standard_log (
            id SERIAL PRIMARY KEY,
            userid INTEGER NOT NULL,
            courseid INTEGER NOT NULL,
            cmid INTEGER NOT NULL,
            action VARCHAR(100) NOT NULL,
            timecreated INTEGER NOT NULL
        );
        """))

def generate_users(num_users):
    users = []
    personas = ['active', 'casual', 'specialist']
    for i in range(1, num_users + 1):
        users.append({
            'id': i,
            'username': fake.user_name(),
            'persona': random.choice(personas)
        })
    return pd.DataFrame(users)

def generate_courses(num_courses):
    courses = []
    difficulties = ['beginner', 'intermediate', 'advanced']
    for i in range(1, num_courses + 1):
        courses.append({
            'id': i,
            'fullname': fake.catch_phrase(),
            'summary': fake.paragraph(),
            'difficulty': random.choice(difficulties),
            'popularity': random.uniform(0, 1)
        })
    return pd.DataFrame(courses)

def generate_resources(num_resources, course_ids):
    resources = []
    mimetypes = [
        'application/pdf', 'text/plain', 'application/msword', 
        'application/vnd.ms-powerpoint', 'video/mp4', 'audio/mpeg',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ]
    for i in range(1, num_resources + 1):
        resources.append({
            'id': i,
            'course': random.choice(course_ids),
            'name': fake.bs(),
            'intro': fake.paragraph(),
            'mimetype': random.choice(mimetypes)
        })
    return pd.DataFrame(resources)

def generate_pages(num_pages, course_ids):
    pages = []
    for i in range(1, num_pages + 1):
        pages.append({
            'id': i,
            'course': random.choice(course_ids),
            'name': fake.bs(),
            'intro': fake.paragraph()
        })
    return pd.DataFrame(pages)

def generate_logs(num_logs, users, courses, content_ids):
    logs = []
    start_date = datetime.now() - timedelta(days=365)
    
    for user in users.itertuples():
        num_user_logs = random.randint(10, 100)
        user_courses = random.sample(courses.index.tolist(), k=min(5, len(courses)))
        
        for _ in range(num_user_logs):
            course = courses.loc[random.choice(user_courses)]
            timestamp = start_date + timedelta(days=random.randint(0, 365))
            
            # Adjust interaction probability based on user persona and course difficulty
            interaction_prob = 0.5
            if user.persona == 'active':
                interaction_prob += 0.3
            elif user.persona == 'casual':
                interaction_prob -= 0.2
            
            if course.difficulty == 'advanced' and user.persona != 'specialist':
                interaction_prob -= 0.2
            
            if random.random() < interaction_prob:
                logs.append({
                    'userid': user.id,
                    'courseid': course.id,
                    'cmid': random.choice(content_ids),
                    'action': 'viewed',
                    'timecreated': int(timestamp.timestamp())
                })
    
    logs_df = pd.DataFrame(logs)
    logs_df['id'] = range(1, len(logs_df) + 1)
    return logs_df

def main():
    create_tables()

    # Generate data
    num_users = 1000
    num_courses = 50
    num_resources = 500
    num_pages = 200

    users = generate_users(num_users)
    courses = generate_courses(num_courses)
    resources = generate_resources(num_resources, courses['id'].tolist())
    pages = generate_pages(num_pages, courses['id'].tolist())
    
    content_ids = resources['id'].tolist() + pages['id'].tolist()
    
    logs = generate_logs(0, users, courses, content_ids) 

    # Save data to database
    users.to_sql('mdl_user', engine, if_exists='append', index=False)
    courses.to_sql('mdl_course', engine, if_exists='append', index=False)
    resources.to_sql('mdl_resource', engine, if_exists='append', index=False)
    pages.to_sql('mdl_page', engine, if_exists='append', index=False)
    logs.to_sql('mdl_logstore_standard_log', engine, if_exists='append', index=False)

    print("Synthetic data generation complete.")

if __name__ == "__main__":
    main()