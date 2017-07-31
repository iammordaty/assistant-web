bash:
	docker exec -it assistant-php bash || true;

index:
	docker exec -it assistant-php php app/console.php collection:index $(filter-out $@,$(MAKECMDGOALS)) || true;

calc:
	docker exec -it assistant-php php app/console.php track:calculate-audio-data -w -s $(filter-out $@,$(MAKECMDGOALS));

logs:
	tail -f app/logs/app.*.log

%:
	@:
