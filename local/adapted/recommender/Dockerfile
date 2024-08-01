FROM python:3.12

WORKDIR /app

# Copy the requirements file into the container
COPY requirements.txt /app

RUN pip install -r requirements.txt

COPY . /app

CMD ["python", "app.py"]
