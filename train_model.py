"""
Train the RPTA ML chatbot.

Dataset:
    data/faqs.json
Expected:
    400 starter questions, 40 intents.

Model:
    TF-IDF word 1-2 grams + character 2-5 grams
    LinearSVC classifier

The exported JSON model is used directly by the PHP chatbot, so the
website does NOT need a Python server at runtime.
"""
import json
import os
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.pipeline import FeatureUnion
from sklearn.svm import LinearSVC
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score

BASE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(BASE, "data", "faqs.json")
OUT = os.path.join(BASE, "data", "ml_model.json")

with open(DATA, encoding="utf-8") as f:
    data = json.load(f)

X = [item["question"] for item in data]
y = [item["intent"] for item in data]

word_vec = TfidfVectorizer(
    ngram_range=(1, 2),
    sublinear_tf=True,
    max_features=1500
)
char_vec = TfidfVectorizer(
    analyzer="char",
    ngram_range=(2, 5),
    sublinear_tf=True,
    max_features=3000
)

features = FeatureUnion([
    ("word", word_vec),
    ("char", char_vec)
])

# Validation result for the project report/viva.
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.20, random_state=42, stratify=y
)
features.fit(X_train)
clf = LinearSVC(C=3.0)
clf.fit(features.transform(X_train), y_train)
validation_accuracy = accuracy_score(y_test, clf.predict(features.transform(X_test)))

# Refit on all 400 questions for the deployed model.
features = FeatureUnion([
    ("word", TfidfVectorizer(
        ngram_range=(1, 2), sublinear_tf=True, max_features=1500
    )),
    ("char", TfidfVectorizer(
        analyzer="char", ngram_range=(2, 5), sublinear_tf=True, max_features=3000
    ))
])
X_all = features.fit_transform(X)
clf = LinearSVC(C=3.0)
clf.fit(X_all, y)

word_vec = features.transformer_list[0][1]
char_vec = features.transformer_list[1][1]

answers = {}
for item in data:
    answers.setdefault(item["intent"], item["answer"])

model = {
    "model_type": "LinearSVC + TF-IDF (word 1-2 grams + character 2-5 grams)",
    "trained_questions": len(data),
    "intents": len(clf.classes_),
    "validation_accuracy": float(validation_accuracy),
    "classes": [str(x) for x in clf.classes_],
    "word_vocab": {str(k): int(v) for k, v in word_vec.vocabulary_.items()},
    "word_idf": [float(x) for x in word_vec.idf_],
    "char_vocab": {str(k): int(v) for k, v in char_vec.vocabulary_.items()},
    "char_idf": [float(x) for x in char_vec.idf_],
    "coef": [[float(x) for x in row] for row in clf.coef_],
    "intercept": [float(x) for x in clf.intercept_],
    "answers": answers
}

with open(OUT, "w", encoding="utf-8") as f:
    json.dump(model, f, ensure_ascii=False, separators=(",", ":"))

print(f"Training questions: {len(data)}")
print(f"Intents: {len(clf.classes_)}")
print(f"Validation accuracy: {validation_accuracy:.2%}")
print(f"Saved model: {OUT}")
