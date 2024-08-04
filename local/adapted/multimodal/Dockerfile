# Use an official Python runtime as a parent image
FROM python:3.9

# Set the working directory in the container
WORKDIR /app/multimodal

# Copy the requirements file into the container first
COPY requirements.txt /app/multimodal/

# Install dependencies
RUN pip install --default-timeout=100 -r requirements.txt

# Set an environment variable to specify the model cache directory
ENV TRANSFORMERS_CACHE=/app/multimodal/bart-large-cnn

# Copy the pre-downloaded model files
COPY ./bart-large-cnn /app/multimodal/model_cache/bart-large-cnn

# List the contents of the model directory for debugging
RUN ls -R /app/multimodal/model_cache/bart-large-cnn

RUN pip install moodlepy

RUN pip install psycopg2-binary

RUN apt-get install -y ca-certificates

RUN pip install Pillow openai

RUN pip install opencv-python

RUN apt-get update
RUN apt install -y libgl1-mesa-glx

# Copy the rest of the application code into the container
COPY . /app/multimodal

# Run app.py when the container launches
CMD ["python", "app.py"]
