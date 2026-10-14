# RPTA ML Chatbot

This portal uses a real machine-learning intent classifier for the Assistant.

## Training data
- 400 starter FAQ questions
- 40 intents
- Sinhala + English questions

## Model
- TF-IDF word 1-2 grams
- TF-IDF character 2-5 grams
- Linear Support Vector Classifier (LinearSVC)
- The trained model is exported to `data/ml_model.json`.

## Runtime
The PHP chatbot loads `data/ml_model.json` directly through `ml_predict.php`.
Therefore, Python is **not required to run the chatbot** after the model has been trained and exported.

## Retraining
If official FAQs are later added/changed:
1. Update `data/faqs.json`.
2. Install Python + scikit-learn:
   `pip install -r requirements.txt`
3. Run:
   `python train_model.py`
4. The new `data/ml_model.json` is generated automatically.

## Project evidence
The included model was trained on 400 questions and 40 intents. A 20% stratified validation split was used during development before refitting the final deployed model on all 400 questions. The recorded validation accuracy is stored in `data/ml_model.json`.
