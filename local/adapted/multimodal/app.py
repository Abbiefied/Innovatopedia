from flask import Flask, request, jsonify
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from sqlalchemy import text as sql_text
from multimodal_conversion import convert_text_to_audio, generate_slides_from_text, generate_video_from_text
import time
import json
import threading
import re
import os
import logging
logging.basicConfig(level=logging.DEBUG)
logging.debug(f"Type of generate_slides_from_text: {type(generate_slides_from_text)}")
logging.debug(f"generate_slides_from_text: {generate_slides_from_text}")

app = Flask(__name__)

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

# Define a base directory for file storage
BASE_DIR = os.getcwd()

def process_job(job_id, input_text, generate_audio, generate_slides, generate_video):
    app.logger.debug(f"Starting job processing: {job_id}")
    job_id = int(job_id)
    app.logger.debug(f"Starting job processing: {job_id}")
    engine = get_db_connection()
    Session = sessionmaker(bind=engine)
    session = Session()
    
    try:
        result = session.execute(sql_text("SELECT * FROM mdl_local_adapted_jobs WHERE id = :job_id"), {"job_id": job_id})
        job_details = result.fetchone()

        if not job_details:
            app.logger.error(f"Job not found: {job_id}")
            return

        total_steps = sum([generate_audio, generate_slides, generate_video])
        current_step = 0
        app.logger.debug(f"Job {job_id}: Starting conversion process")

        files = []

        if generate_audio:
            app.logger.info(f"Job {job_id}: Generating audio")
            audio_file = os.path.join(BASE_DIR, f"audio_{int(time.time())}.mp3")
            try:
                convert_text_to_audio(input_text, audio_file)
                if os.path.exists(audio_file):
                    files.append(audio_file)
                    app.logger.info(f"Job {job_id}: Audio file saved at {audio_file}")
                else:
                    app.logger.error(f"Job {job_id}: Failed to save audio file at {audio_file}")
            except Exception as e:
                app.logger.error(f"Job {job_id}: Error generating audio - {str(e)}")

            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET files = :files WHERE id = :job_id"), 
                            {"files": json.dumps(files), "job_id": job_id})
            session.commit()
            app.logger.info(f"Job {job_id}: Audio generation complete")
            
            current_step += 1
            progress = int((current_step / total_steps) * 80)
            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET progress = :progress WHERE id = :job_id"), 
                            {"progress": progress, "job_id": job_id})
            session.commit()

        if generate_slides:
            app.logger.debug(f"Job {job_id}: Generating slides")
            slides_file = os.path.join(BASE_DIR, f"slides_{int(time.time())}.pptx")
            try:
                if callable(generate_slides_from_text):
                    generate_slides_from_text(input_text, slides_file)
                    if os.path.exists(slides_file):
                        files.append(slides_file)
                        app.logger.info(f"Job {job_id}: Slides file saved at {slides_file}")
                    else:
                        app.logger.error(f"Job {job_id}: Failed to save slides file at {slides_file}")
                else:
                    app.logger.error(f"generate_slides_from_text is not callable. Type: {type(generate_slides_from_text)}")
                    raise TypeError("generate_slides_from_text is not a callable function")
            except Exception as e:
                app.logger.error(f"Job {job_id}: Error generating slides - {str(e)}")
                
            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET files = :files WHERE id = :job_id"), 
                            {"files": json.dumps(files), "job_id": job_id})
            session.commit()
            app.logger.debug(f"Job {job_id}: Slides generation complete")

            current_step += 1
            progress = int((current_step / total_steps) * 100)
            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET progress = :progress WHERE id = :job_id"), 
                            {"progress": progress, "job_id": job_id})
            session.commit()
            
        if generate_video:
            app.logger.info(f"Job {job_id}: Generating video")
            video_file = os.path.join(BASE_DIR, f"video_{int(time.time())}.mp4")
            try:
                generate_video_from_text(input_text, video_file)
                if os.path.exists(video_file):
                    files.append(video_file)
                    app.logger.info(f"Job {job_id}: Video file saved at {video_file}")
                else:
                    app.logger.error(f"Job {job_id}: Failed to save video file at {video_file}")
            except Exception as e:
                app.logger.error(f"Job {job_id}: Error generating video - {str(e)}")
            
            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET files = :files WHERE id = :job_id"), 
                            {"files": json.dumps(files), "job_id": job_id})
            session.commit()
            app.logger.info(f"Job {job_id}: Video generation complete")
            
            current_step += 1
            progress = 100
            progress = int((current_step / total_steps) * 100)
            session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET progress = :progress WHERE id = :job_id"), 
                            {"progress": progress, "job_id": job_id})
            session.commit()
        
        session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET progress = 100 WHERE id = :job_id"), {"job_id": job_id})
        session.commit()
        app.logger.info(f"Job {job_id} completed successfully")
        session.execute(sql_text("UPDATE mdl_local_adapted_jobs SET files = :files WHERE id = :job_id"), 
                {"files": json.dumps(files), "job_id": job_id})
        session.commit()
        app.logger.info(f"Files {files} saved successfully")
    except Exception as e:
        app.logger.error(f"Error processing job {job_id}: {str(e)}")
        session.rollback()
    finally:
        session.close()

@app.route('/generate', methods=['POST'])
def generate():
    data = request.json
    app.logger.debug(f"Received generate request: {data}")
    job_id = data.get('job_id')
    app.logger.debug(f"job id: {job_id}")
    if not job_id:
        return jsonify({"error": "No job_id provided"}), 400
    input_text = data['text']
    generate_audio = data.get('generate_audio', False)
    generate_slides = data.get('generate_slides', False)
    generate_video = data.get('generate_video', False)
    app.logger.debug("About to start processing thread")
    threading.Thread(target=process_job, args=(job_id, input_text, generate_audio, generate_slides, generate_video)).start()
    app.logger.debug("Processing thread started")
    return jsonify({"message": "Job started"})

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({"status": "healthy"}), 200

if __name__ == '__main__':

    app.run(host='0.0.0.0', port=5001)