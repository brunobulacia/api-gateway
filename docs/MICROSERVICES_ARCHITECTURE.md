# EduPay — Arquitectura de Microservicios con RabbitMQ

> Documento de referencia para el equipo. Describe cómo cada microservicio se
> integra con el **API Gateway (Laravel)** a través de **RabbitMQ** como bus de
> mensajes.

---

## 1. Visión general

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Clientes / Apps                              │
│                  (Mobile App, Web App, Admin)                       │
└───────────────────────────┬─────────────────────────────────────────┘
                            │ HTTP / REST
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   API Gateway — Laravel                             │
│  • Autenticación JWT        • Rate limiting                         │
│  • Enrutamiento de peticiones  • Respuesta unificada al cliente     │
│  Publisher  ◄──────────────────────────────►  Consumer             │
└──────┬─────────────────────────────────────────────┬───────────────┘
       │                RabbitMQ                      │
       │         Exchange: edupay (topic)             │
       │                                              │
  ┌────▼────────┐    ┌──────────────┐    ┌───────────▼───────────┐
  │  ms-ia      │    │  ms-pagos    │    │  ms-academico         │
  │  Python /   │    │  NestJS /    │    │  Spring Boot /        │
  │  FastAPI    │    │  TypeScript  │    │  Java                 │
  └─────────────┘    └──────────────┘    └───────────────────────┘
```

---

## 2. Broker — RabbitMQ

| Parámetro        | Valor                          |
|------------------|--------------------------------|
| Exchange         | `edupay`                       |
| Tipo de exchange | `topic`                        |
| Durabilidad      | `durable = true`               |
| Patrón principal | **RPC** (reply_to + correlation_id) |

### 2.1 Patrón RPC

El API Gateway actúa como **cliente RPC**:

1. Declara una cola de respuesta temporal exclusiva (p. ej. `amq.rabbitmq.reply-to`).
2. Publica el mensaje en el exchange `edupay` con:
   - `routing_key` → cola del microservicio destino
   - `reply_to` → cola temporal propia
   - `correlation_id` → UUID de la petición
3. Espera la respuesta en su cola temporal.
4. El microservicio procesa y publica la respuesta de vuelta al `reply_to`.

```
Gateway                     RabbitMQ                    Microservicio
   │                            │                              │
   │── publish(rk, reply_to) ──►│── route to queue ──────────►│
   │                            │                              │ (procesa)
   │◄── consume(reply_to) ──────│◄── publish(reply_to) ────────│
```

---

## 3. Microservicios

### 3.1 ms-ia (Python / FastAPI) — este repositorio

**Responsabilidad:** Inteligencia Artificial — predicción de riesgo de mora,
clustering de familias, OCR de comprobantes.

| Cola consumida        | Routing Key           | Descripción                        |
|-----------------------|-----------------------|------------------------------------|
| `ms_ia.risk_score`    | `ms_ia.risk_score`    | Predice el riesgo de mora          |
| `ms_ia.cluster`       | `ms_ia.cluster`       | Clasifica a la familia en un grupo |
| `ms_ia.payment_event` | `ms_ia.payment_event` | Registra eventos de pago           |
| `ms_ia.ocr`           | `ms_ia.ocr`           | Analiza comprobantes (binario)     |

#### Formato de mensajes entrantes

**`ms_ia.risk_score`**
```json
{
  "familyId": "fam_001",
  "features": {
    "months_enrolled": 12,
    "total_payments": 10,
    "on_time_payments": 8,
    "average_days_late": 3.2,
    "max_consecutive_late": 2,
    "has_paid_annual_ever": true,
    "preferred_payment_method_qr": false,
    "preferred_payment_method_stripe": true,
    "preferred_payment_method_blockchain": false,
    "uses_mobile_app": true,
    "has_discount": false,
    "is_after_carnaval": false
  }
}
```

**Respuesta:**
```json
{
  "familyId": "fam_001",
  "riskScore": 0.73,
  "riskLevel": "HIGH",
  "modelVersion": "1.0.0",
  "predictionDate": "2026-05-30",
  "_publishedAt": "2026-05-30T14:22:00Z",
  "_service": "ms-ia"
}
```

---

**`ms_ia.cluster`**
```json
{
  "familyId": "fam_001",
  "features": {
    "avg_monthly_income": 3500.0,
    "num_children": 2,
    "payment_regularity_score": 0.85,
    "total_debt": 1200.0
  }
}
```

**Respuesta:**
```json
{
  "familyId": "fam_001",
  "cluster": 2,
  "clusterLabel": "Familia Estable",
  "recommendedAction": "Ofrecer plan anual con descuento",
  "modelVersion": "1.0.0",
  "computedAt": "2026-05-30T14:22:00Z",
  "_service": "ms-ia"
}
```

---

**`ms_ia.payment_event`**
```json
{
  "familyId": "fam_001",
  "paymentId": "pay_999",
  "amount": 450.00,
  "currency": "BOB",
  "method": "QR",
  "paidAt": "2026-05-30T10:00:00Z",
  "dueDate": "2026-05-28"
}
```

**Respuesta:**
```json
{
  "status": "received",
  "familyId": "fam_001",
  "paymentId": "pay_999",
  "_service": "ms-ia"
}
```

---

#### Stack técnico

| Elemento       | Tecnología                    |
|----------------|-------------------------------|
| Lenguaje       | Python 3.14                   |
| Framework      | FastAPI 0.136 + Uvicorn       |
| Base de datos  | MongoDB Atlas (motor async)   |
| ML             | LightGBM, scikit-learn, Pillow|
| RabbitMQ       | aio-pika 9.5.5                |
| Modelos        | `models_store/` (`.pkl` + `meta.json`) |

#### Variables de entorno requeridas

```env
MONGODB_URI=amqp://...
MONGODB_DATABASE=edupay_ia
RABBITMQ_URL=amqp://guest:guest@localhost:5672/
```

#### Cómo levantar en desarrollo

```bash
# 1. Activar entorno virtual
source .venv/bin/activate

# 2. Instalar dependencias
pip install -r requirements.txt

# 3. Levantar servidor (con hot-reload)
fastapi dev main.py
```

> **Nota:** Si RabbitMQ no está disponible al iniciar, el microservicio arranca
> en **modo HTTP-only** (solo los endpoints REST funcionan, el consumer queda
> deshabilitado hasta el próximo reinicio).

---

### 3.2 ms-pagos (NestJS / TypeScript)

**Responsabilidad:** Gestión de pagos, generación de cuotas, integración con
pasarelas de pago (Stripe, QR, blockchain).

| Cola consumida              | Routing Key                 | Descripción                     |
|-----------------------------|-----------------------------|---------------------------------|
| `ms_pagos.create_payment`   | `ms_pagos.create_payment`   | Crea un nuevo pago              |
| `ms_pagos.get_balance`      | `ms_pagos.get_balance`      | Consulta saldo de una familia   |
| `ms_pagos.process_webhook`  | `ms_pagos.process_webhook`  | Procesa webhook de pasarela     |

#### Stack técnico

| Elemento       | Tecnología                          |
|----------------|-------------------------------------|
| Lenguaje       | TypeScript                          |
| Framework      | NestJS                              |
| RabbitMQ       | `@nestjs/microservices` (AMQP)      |
| Base de datos  | PostgreSQL (TypeORM)                |

#### Integración NestJS ↔ RabbitMQ (referencia)

```typescript
// main.ts
const app = await NestFactory.createMicroservice<MicroserviceOptions>(AppModule, {
  transport: Transport.RMQ,
  options: {
    urls: [process.env.RABBITMQ_URL],
    queue: 'ms_pagos.create_payment',
    queueOptions: { durable: true },
    exchange: 'edupay',
    exchangeType: 'topic',
  },
});

// En el controller
@MessagePattern('ms_pagos.create_payment')
async createPayment(@Payload() data: CreatePaymentDto) {
  return this.paymentsService.create(data);
}
```

---

### 3.3 ms-academico (Spring Boot / Java)

**Responsabilidad:** Gestión académica — matrículas, estudiantes, calificaciones,
asistencias.

| Cola consumida                    | Routing Key                       | Descripción                       |
|-----------------------------------|-----------------------------------|-----------------------------------|
| `ms_academico.enroll_student`     | `ms_academico.enroll_student`     | Matricula un alumno               |
| `ms_academico.get_student`        | `ms_academico.get_student`        | Consulta datos de un alumno       |
| `ms_academico.update_attendance`  | `ms_academico.update_attendance`  | Actualiza asistencia              |

#### Stack técnico

| Elemento       | Tecnología                             |
|----------------|----------------------------------------|
| Lenguaje       | Java 21                                |
| Framework      | Spring Boot 3.x                        |
| RabbitMQ       | `spring-boot-starter-amqp` (RabbitMQ) |
| Base de datos  | PostgreSQL (JPA / Hibernate)           |

#### Integración Spring Boot ↔ RabbitMQ (referencia)

```java
// RabbitMQConfig.java
@Bean
public TopicExchange edupayExchange() {
    return new TopicExchange("edupay", true, false);
}

@Bean
public Queue enrollQueue() {
    return QueueBuilder.durable("ms_academico.enroll_student").build();
}

@Bean
public Binding enrollBinding(Queue enrollQueue, TopicExchange exchange) {
    return BindingBuilder.bind(enrollQueue).to(exchange).with("ms_academico.enroll_student");
}

// AcademicoConsumer.java
@RabbitListener(queues = "ms_academico.enroll_student")
public EnrollResponse enroll(EnrollStudentRequest request) {
    return academicoService.enroll(request);
}
```

---

## 4. API Gateway (Laravel)

**Responsabilidad:** Punto de entrada único. Autentica al usuario, enruta la
petición al microservicio correcto vía RabbitMQ y devuelve la respuesta al cliente.

### Librería recomendada

```bash
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

### Ejemplo de envío RPC desde Laravel

```php
// app/Services/RabbitMQRpcClient.php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQRpcClient
{
    public function call(string $queue, array $payload): array
    {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'), config('rabbitmq.port'),
            config('rabbitmq.user'), config('rabbitmq.password')
        );
        $channel = $connection->channel();

        // Cola temporal de respuesta
        [$callbackQueue] = $channel->queue_declare('', false, false, true, false);
        $correlationId   = uniqid('', true);

        $message = new AMQPMessage(
            json_encode($payload),
            [
                'content_type'   => 'application/json',
                'correlation_id' => $correlationId,
                'reply_to'       => $callbackQueue,
                'delivery_mode'  => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $channel->basic_publish($message, 'edupay', $queue);

        $response = null;
        $channel->basic_consume(
            $callbackQueue, '', false, true, false, false,
            function (AMQPMessage $msg) use ($correlationId, &$response) {
                if ($msg->get('correlation_id') === $correlationId) {
                    $response = json_decode($msg->body, true);
                }
            }
        );

        while ($response === null) {
            $channel->wait(null, false, 10); // timeout 10s
        }

        $channel->close();
        $connection->close();

        return $response;
    }
}
```

```php
// Uso en un Controller de Laravel
public function getRiskScore(Request $request, string $familyId): JsonResponse
{
    $rpc = app(RabbitMQRpcClient::class);
    $result = $rpc->call('ms_ia.risk_score', [
        'familyId' => $familyId,
        'features' => $request->validated(),
    ]);
    return response()->json($result);
}
```

---

## 5. Convención de nombres de colas y routing keys

| Patrón              | Ejemplo                      | Descripción                          |
|---------------------|------------------------------|--------------------------------------|
| `ms_<servicio>.<acción>` | `ms_ia.risk_score`      | Cola consumida por el microservicio  |
| `gateway.<dominio>.<acción>.reply` | `gateway.ai.risk_score.reply` | Routing key de respuesta (fire-and-forget) |

---

## 6. Docker Compose (desarrollo local)

```yaml
version: "3.9"
services:

  rabbitmq:
    image: rabbitmq:3.13-management
    ports:
      - "5672:5672"    # AMQP
      - "15672:15672"  # Management UI  →  http://localhost:15672
    environment:
      RABBITMQ_DEFAULT_USER: guest
      RABBITMQ_DEFAULT_PASS: guest

  ms-ia:
    build: ./ms-ia
    env_file: ./ms-ia/.env
    environment:
      RABBITMQ_URL: amqp://guest:guest@rabbitmq:5672/
    depends_on:
      - rabbitmq
    ports:
      - "8000:8000"

  ms-pagos:
    build: ./ms-pagos
    environment:
      RABBITMQ_URL: amqp://guest:guest@rabbitmq:5672/
    depends_on:
      - rabbitmq
    ports:
      - "3000:3000"

  ms-academico:
    build: ./ms-academico
    environment:
      SPRING_RABBITMQ_HOST: rabbitmq
    depends_on:
      - rabbitmq
    ports:
      - "8080:8080"

  api-gateway:
    build: ./api-gateway
    environment:
      RABBITMQ_HOST: rabbitmq
    depends_on:
      - rabbitmq
    ports:
      - "80:80"
```

> Acceder al panel de RabbitMQ en desarrollo: http://localhost:15672
> (usuario: `guest` / contraseña: `guest`)

---

## 7. Resumen de puertos

| Servicio       | Puerto HTTP | Descripción                  |
|----------------|-------------|------------------------------|
| API Gateway    | 80          | Entrada pública              |
| ms-ia          | 8000        | REST interno + RabbitMQ      |
| ms-pagos       | 3000        | REST interno + RabbitMQ      |
| ms-academico   | 8080        | REST interno + RabbitMQ      |
| RabbitMQ AMQP  | 5672        | Broker de mensajes           |
| RabbitMQ UI    | 15672       | Panel de administración      |
