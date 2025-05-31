API_KEY:= 184F66085DA76CDF5300FE2C6E229238
PORT:= 8080

ticket:
	curl -v -H "X-API-Key: $(API_KEY)" -X POST \
	-d '{"name": "Angry Customer", "email": "angry.customer@example.com", "subject": "Test complaint", "message": "It doesnt work"}' \
	"http://localhost:${PORT}/api/tickets.json" 

list-tickets:
	curl -v -H "X-API-Key: $(API_KEY)" -X GET \
	"http://localhost:${PORT}/api/tickets.json"