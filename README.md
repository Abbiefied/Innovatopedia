 # Innovatopedia: Moodle Enhanced Learning Platform

This project enhances the Moodle Learning Management System with advanced features including content recommendations, multimodal content generation, and an AI-powered chatbot by integrating the AdaptED Plugin.

## Features

- **Recommender System:** Provides personalized content recommendations using a hybrid approach combining collaborative filtering, content-based filtering and score boosting.
- **Multimodal Content Generation:**
  - Convert text to audio
  - Generate presentation slides from text
  - Create video content from text and audio
- **AI Chatbot:** An intelligent assistant integrated into Moodle to help users with course information, assignments, grades, and deadlines.
- **Nginx Web Server:** Configured to serve the Moodle application securely.

## Architecture

The system is containerized using Docker and orchestrated with Docker Compose. It consists of the following services:

- **db:** PostgreSQL database
- **moodle:** Main Moodle application
- **recommender:** Content recommendation service
- **multimodal:** Multimodal content generation service
- **nginx:** Web server for routing and SSL termination

## Setup and Installation

1. Ensure you have Docker and Docker Compose installed on your system.
2. Clone this repository.
3. Download required AI models by running `python download_models.py` in the `multimodal` directory
4. Configure the necessary environment variables in `docker-compose.yaml`.
5. Run `docker-compose up -d` to start all services.

## Configuration

1. In the Moodle admin panel, navigate to Site Administration> Plugins > Local plugins > Adapted > Settings.
2. Set up the API key for external services.

## Usage

### Recommender System

The recommender system automatically provides content suggestions to users based on their interactions and preferences within Moodle.

### Multimodal Content Generation

To generate multimodal content:

1. Navigate to the Multimodal Generation page in the Moodle admin panel.
2. Upload a text file and select the desired output formats (audio, slides, video).
3. The system will process the request asynchronously and store the generated files.

### Chatbot

The chatbot is accessible within the Moodle interface and can answer questions about courses, assignments, grades, and upcoming deadlines.

## Development

- The project uses Python for backend services and PHP for Moodle customizations.
- Machine learning models (SVD, TF-IDF, Random Forest) are used for content recommendations.
- Natural language processing techniques are employed for the chatbot and content summarization.
- The `facebook/bart-large-cnn` model is used for text summarization.

## Contact

[Your contact information or where to report issues]

