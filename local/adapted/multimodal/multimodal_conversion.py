import sys
from gtts import gTTS
from transformers import pipeline, AutoTokenizer, AutoModelForSeq2SeqLM 
from moviepy.editor import TextClip, CompositeVideoClip, ColorClip
from pptx import Presentation
from pptx.util import Inches
import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont
import logging
import os

# Set up logging
logging.basicConfig(level=logging.DEBUG)

# Set the model cache directory
os.environ['TRANSFORMERS_CACHE'] = '/app/multimodal/bart-large-cnn'

def convert_text_to_audio(text, audio_file):
    logging.debug("Starting audio conversion")
    tts = gTTS(text=text, lang='en')
    tts.save(audio_file)

def generate_slides_from_text(text, slides_file):
    logging.debug("Starting slides conversion")
    
    # model_path = '/app/multimodal/bart-large-cnn'
    model_path = './bart-large-cnn'
    
    try:
        logging.debug(f"Current working directory: {os.getcwd()}")
        # logging.debug(f"Contents of /app: {os.listdir('/app')}")
        # logging.debug(f"Contents of model path: {os.listdir(model_path)}")
        
        logging.debug(f"Loading tokenizer from: {model_path}")
        tokenizer = AutoTokenizer.from_pretrained(model_path, local_files_only=True)
        logging.debug("Tokenizer loaded successfully")
        
        logging.debug(f"Loading model from: {model_path}")
        model = AutoModelForSeq2SeqLM.from_pretrained(model_path, local_files_only=True)
        logging.debug("Model loaded successfully")
        
        summarizer = pipeline("summarization", model=model, tokenizer=tokenizer)
        
        summary = summarizer(text, max_length=100, min_length=30, do_sample=False)[0]['summary_text']
        
        prs = Presentation()
        slide_layout = prs.slide_layouts[1]  # Title and Content layout
        slide = prs.slides.add_slide(slide_layout)
        title = slide.shapes.title
        content = slide.placeholders[1]

        title.text = "Summary Slide"
        content.text = summary

        prs.save(slides_file)
        logging.debug("Slides generated successfully")
    except Exception as e:
        logging.error(f"Error in generate_slides: {str(e)}")
        logging.error(f"Current working directory: {os.getcwd()}")
        # logging.error(f"Contents of /app: {os.listdir('/app')}")
        # if os.path.exists('/app/model_cache'):
        #     logging.error(f"Contents of /app/model_cache: {os.listdir('/app/model_cache')}")
        raise
    
def generate_video_from_text(text, video_file):
    logging.debug("Starting video conversion")
    
    # Video settings
    width, height = 1280, 720
    fps = 24
    duration = 10  # seconds
    
    # Create a VideoWriter object
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(video_file, fourcc, fps, (width, height))
    
    # Create a black background image
    background = np.zeros((height, width, 3), dtype=np.uint8)
    
    # Use a default font (you might need to specify the full path to a .ttf file)
    try:
        font = ImageFont.truetype("arial.ttf", 30)
    except IOError:
        font = ImageFont.load_default()
    
    lines = text.split('\n')
    
    for frame in range(fps * duration):
        img = Image.fromarray(background)
        draw = ImageDraw.Draw(img)
        
        y = 50
        for i, line in enumerate(lines):
            # Only show lines for 2 seconds each
            if i * fps * 2 <= frame < (i + 1) * fps * 2:
                draw.text((width // 2, y), line, font=font, fill=(255, 255, 255), anchor="mm")
            y += 50
        
        # Convert PIL Image to OpenCV format
        cv_img = cv2.cvtColor(np.array(img), cv2.COLOR_RGB2BGR)
        out.write(cv_img)
    
    out.release()
    logging.debug("Video generated successfully")
    
if __name__ == "__main__":
    logging.debug("Starting app.py")
    text = sys.argv[1]
    audio_file = sys.argv[2]
    slides_file = sys.argv[3]
    video_file = sys.argv[4]
    generate_audio = bool(int(sys.argv[5]))
    generate_slides_flag = bool(int(sys.argv[6])) 
    generate_video = bool(int(sys.argv[7]))

    if generate_audio:
        convert_text_to_audio(text, audio_file)
    
    if generate_slides_flag:
        generate_slides_from_text(text, slides_file)

    if generate_video:
        generate_video_from_text(text, video_file)