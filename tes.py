import requests

url = "http://localhost:8000/api/analysis-feedback"

data = {
    "commodity_id": "1",
    "analysis_date": "2025-09-20",
    "feedback_type": "positive"
}

response = requests.post(url, json=data)

print(response.status_code)
print(response.json())