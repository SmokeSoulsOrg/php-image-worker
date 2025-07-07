# php-image-worker

The `php-image-worker` is a background microservice responsible for downloading and caching
pornstar image assets based on messages received from the `image-download` RabbitMQ queue.
It is part of a larger microservices system designed for efficient processing of a daily
pornstar feed.

## 🧠 Responsibilities

- Listens to the `image-download` RabbitMQ queue for image download requests.
- Downloads images to a shared volume (`/pornstar-images`).
- After a successful download, publishes a message to the `image-update` queue with metadata
  about the cached image (e.g., `local_path`, `url`, `type`).

These messages are later consumed by the `php-api-service` to update the associated
`PornstarThumbnailUrl` entities.

## ⚙️ Tech Stack

- PHP 8.4
- Laravel 12
- Laravel Queue + RabbitMQ (via AMQP)
- Docker

## 🚀 Usage

This service is designed to run as a long-lived queue worker and is started automatically
within the `infra-deployment` Docker setup. It listens for image download jobs and processes
them in real time.

## 🧪 Testing

Run the test suite inside the infra-deployment container:

```bash
docker exec -it  infra-deployment-php-image-worker-1 php artisan test
```

## 📂 Environment

Set the required environment variables via `.env` or using the mounted file
`.envs/php-image-worker.env`.

## 🔗 Related

- Receives jobs from: `php-feed-ingestor`
- Sends updates to: `php-api-service`  
