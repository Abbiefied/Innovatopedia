from transformers import AutoTokenizer, AutoModelForSeq2SeqLM

def download_model(model_name, save_directory):
    print(f"Downloading tokenizer for {model_name}")
    tokenizer = AutoTokenizer.from_pretrained(model_name)
    tokenizer.save_pretrained(save_directory)
    print(f"Downloading model for {model_name}")
    model = AutoModelForSeq2SeqLM.from_pretrained(model_name)
    model.save_pretrained(save_directory)
    print(f"Downloaded {model_name} successfully")

if __name__ == "__main__":
    model_name = "facebook/bart-large-cnn"
    save_directory = "./bart-large-cnn"
    download_model(model_name, save_directory)