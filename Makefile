API_KEY:= ACBCA7F760A71D4D28B64E4B1CDC820C
PORT:= 8080
DEBUG:= 0
XDEBUG_HEADER:= $(if $(DEBUG),-H "Cookie: XDEBUG_SESSION=PHPSTORM",)
HOST ?= http://localhost:8080

ticket:
	curl -v -H "X-API-Key: $(API_KEY)" -X POST \
	-d '{"name": "Angry Customer", "email": "angry.customer@example.com", "subject": "Test complaint", "message": "It doesnt work"}' \
	"${HOST}/api/tickets.json" 

list-tickets:
	curl -v -H "X-API-Key: $(API_KEY)" -X GET $(XDEBUG_HEADER) \
	"${HOST}/api/tickets.json" | jq

list-last:
	export LAST_ID=$(shell curl -s -H "X-API-Key: $(API_KEY)" -X GET \
	"${HOST}/api/tickets.json" | jq -r '.tickets[0].id'); \
	echo "Last ticket ID: $${LAST_ID}"; \
	curl -v -H "X-API-Key: $(API_KEY)" -X GET $(XDEBUG_HEADER) \
	"${HOST}/api/tickets/$${LAST_ID}.json" | jq

add-note:
	export LAST_ID=$(shell curl -s -H "X-API-Key: $(API_KEY)" -X GET \
	"${HOST}/api/tickets.json" | jq -r '.tickets[0].id'); \
	echo "Last ticket ID: $${LAST_ID}"; \
	curl -v -H "X-API-Key: $(API_KEY)" $(XDEBUG_HEADER) -X POST \
	-d '{"thread_type": "note", "message": "This is an internal note visible only to staff", "staffId": 1, "alert": false}' \
	${HOST}/api/tickets/$${LAST_ID}/add_thread.json

add-reply:
	export LAST_ID=$(shell curl -s -H "X-API-Key: $(API_KEY)" -X GET \
	"${HOST}/api/tickets.json" | jq -r '.tickets[0].id'); \
	echo "Last ticket ID: $${LAST_ID}"; \
	curl -v -H "X-API-Key: $(API_KEY)" $(XDEBUG_HEADER) -X POST \
	-d '{"thread_type": "response", "message": "This is an reply to the user", "staffId": 1, "alert": false}' \
	${HOST}/api/tickets/$${LAST_ID}/add_thread.json

add-email:
	export LAST_ID=$(shell curl -s -H "X-API-Key: $(API_KEY)" -X GET \
	"${HOST}/api/tickets.json" | jq -r '.tickets[0].id'); \
	echo "Last ticket ID: $${LAST_ID}"; \
	curl -v -H "X-API-Key: $(API_KEY)" $(XDEBUG_HEADER) -X POST \
	-d '{"thread_type": "response", "as_client": true, "message": "When are you going to fix my problem", "staffId": 1, "alert": false}' \
	${HOST}/api/tickets/$${LAST_ID}/add_thread.json

lookup:
	curl -v -H "X-API-Key: $(API_KEY)" -X GET $(XDEBUG_HEADER) \
	"${HOST}/api/tickets/number/$${TICKET}.json" | jq
