import unittest
from multimodal_conversion import convert_text_to_audio, generate_slides_from_text, generate_video_from_text
import os

class TestMultimodalConversion(unittest.TestCase):

    def setUp(self):
        # Setup code to run before each test
        self.text = "At its most basic, neuroscience is the study of the nervous system – from structure to function, development to degeneration, in health and in disease. It covers the whole nervous system, with a primary focus on the brain. Incredibly complex, our brains define who we are and what we do. They store our memories and allow us to learn from them. Our brain cells and their circuits create new thoughts, ideas and movements and reinforce old ones. Their individual connections (synapses) are responsible for a baby’s first steps and every record-breaking athletic performance, with each thought and movement requiring exquisitely precise timing and connections."
        self.audio_file = "test_audio.mp3"
        self.slides_file = "test_slides.pptx"
        self.video_file = "test_video.mp4"

    def tearDown(self):
        # Cleanup code to run after each test
        if os.path.exists(self.audio_file):
            os.remove(self.audio_file)
        if os.path.exists(self.slides_file):
            os.remove(self.slides_file)
        if os.path.exists(self.video_file):
            os.remove(self.video_file)

    def test_convert_text_to_audio(self):
        print("Running test_convert_text_to_audio...")
        convert_text_to_audio(self.text, self.audio_file)
        self.assertTrue(os.path.exists(self.audio_file))
        print("test_convert_text_to_audio passed.")

    def test_generate_slides_from_text(self):
        print("Running test_generate_slides_from_text...")
        summaries = generate_slides_from_text(self.text, self.slides_file)
        self.assertTrue(os.path.exists(self.slides_file))
        self.assertIsInstance(summaries, list)
        for summary in summaries:
            self.assertIsInstance(summary, str)
        print("test_generate_slides_from_text passed.")

    def test_generate_video_from_text(self):
        print("Running test_generate_video_from_text...")
        convert_text_to_audio(self.text, self.audio_file)  # Ensure audio file exists
        generate_video_from_text(self.text, self.audio_file, self.video_file)
        self.assertTrue(os.path.exists(self.video_file))
        print("test_generate_video_from_text passed.")

if __name__ == "__main__":
    suite = unittest.TestLoader().loadTestsFromTestCase(TestMultimodalConversion)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    print(f"Results: {result}")
    print(f"Number of tests run: {result.testsRun}")
    print(f"Number of tests succeeded: {len(result.successes)}")
    print(f"Number of tests failed: {len(result.failures)}")