# EduPay — API Gateway: Guía de Pruebas

Base URL: `http://localhost`  
Autenticación: **Bearer JWT** en header `Authorization`

---

## Levantar el stack

```bash
# Desde la raíz del proyecto
docker compose up --build -d
```

| Servicio | URL | Estado |
|---|---|---|
| API Gateway (Laravel) | http://localhost | ✅ activo |
| ms-ia (Python/FastAPI) | http://localhost:8000 | ✅ activo |
| RabbitMQ Management | http://localhost:15672 | ✅ activo (guest/guest) |
| ms-pagos (NestJS) | http://localhost:3000 | ⏳ pendiente |
| ms-academico (Spring Boot) | http://localhost:8080 | ⏳ pendiente |

---

## Flujo de autenticación

Todas las rutas excepto `/api/auth/register` y `/api/auth/login` requieren token JWT.

### 1. Registrar usuario

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin EduPay",
    "email": "admin@edupay.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Respuesta 201:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

---

### 2. Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@edupay.com",
    "password": "password123"
  }'
```

**Respuesta 200:** igual al registro.

---

### 3. Ver usuario actual

```bash
curl http://localhost/api/auth/me \
  -H "Authorization: Bearer <TOKEN>"
```

**Respuesta 200:**
```json
{
  "id": 1,
  "name": "Admin EduPay",
  "email": "admin@edupay.com"
}
```

---

### 4. Refrescar token

```bash
curl -X POST http://localhost/api/auth/refresh \
  -H "Authorization: Bearer <TOKEN>"
```

---

### 5. Logout

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Módulo IA (`ms-ia`) ✅ funcional

> Requiere `ms-ia` corriendo. Enruta vía RabbitMQ → exchange `edupay`.

### Guardar token en variable (bash)

```bash
TOKEN=$(curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edupay.com","password":"password123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
```

---

### GET Risk Score — `POST /api/ia/families/{familyId}/risk-score`

Predice el riesgo de mora de una familia.

```bash
curl -X POST http://localhost/api/ia/families/fam_001/risk-score \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
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
  }'
```

**Respuesta 200:**
```json
{
  "familyId": "fam_001",
  "riskScore": 0.73,
  "riskLevel": "HIGH",
  "modelVersion": "lgbm-v1.0.0",
  "predictionDate": "2026-05-30",
  "_publishedAt": "2026-05-30T14:22:00Z",
  "_service": "ms-ia"
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `riskScore` | float 0–1 | Probabilidad de mora |
| `riskLevel` | string | `LOW` / `MEDIUM` / `HIGH` |

---

### Clustering de familia — `POST /api/ia/families/{familyId}/cluster`

Clasifica a la familia en un segmento socioeconómico.

```bash
curl -X POST http://localhost/api/ia/families/fam_001/cluster \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "avg_monthly_income": 3500.0,
    "num_children": 2,
    "payment_regularity_score": 0.85,
    "total_debt": 1200.0
  }'
```

**Respuesta 200:**
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

### Registrar evento de pago — `POST /api/ia/payment-events`

Notifica un pago al módulo de IA para actualizar el historial predictivo.

```bash
curl -X POST http://localhost/api/ia/payment-events \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "familyId": "fam_001",
    "paymentId": "pay_999",
    "amount": 450.00,
    "currency": "BOB",
    "method": "QR",
    "paidAt": "2026-05-30T10:00:00Z",
    "dueDate": "2026-05-28"
  }'
```

**Respuesta 201:**
```json
{
  "status": "received",
  "familyId": "fam_001",
  "paymentId": "pay_999",
  "_service": "ms-ia"
}
```

---

### OCR de comprobante — `POST /api/ia/ocr`

Analiza una imagen o PDF de comprobante de pago.

```bash
curl -X POST http://localhost/api/ia/ocr \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@/ruta/al/comprobante.jpg"
```

**Respuesta 200:**
```json
{
  "status": "ok",
  "extracted": {
    "amount": "450.00",
    "date": "2026-05-30",
    "reference": "REF123456"
  },
  "_service": "ms-ia"
}
```

> Formatos aceptados: `jpg`, `jpeg`, `png`, `pdf`. Tamaño máximo: 10 MB.

---

## Módulo Pagos (`ms-pagos`) ⏳ pendiente

> Estos endpoints responden cuando `ms-pagos` (NestJS) esté levantado.  
> Sin él, la respuesta será un timeout de 10 segundos.

### Crear pago — `POST /api/pagos/payments`

```bash
curl -X POST http://localhost/api/pagos/payments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "familyId": "fam_001",
    "amount": 450.00,
    "currency": "BOB",
    "method": "QR",
    "dueDate": "2026-06-01"
  }'
```

| `method` | Descripción |
|---|---|
| `QR` | Pago por QR boliviano |
| `STRIPE` | Tarjeta internacional |
| `BLOCKCHAIN` | Pago cripto |

---

### Consultar saldo — `GET /api/pagos/families/{familyId}/balance`

```bash
curl http://localhost/api/pagos/families/fam_001/balance \
  -H "Authorization: Bearer $TOKEN"
```

---

### Webhook de pasarela — `POST /api/pagos/webhooks`

```bash
curl -X POST http://localhost/api/pagos/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "stripe",
    "event": "payment_intent.succeeded",
    "paymentIntentId": "pi_abc123",
    "amount": 45000,
    "currency": "usd"
  }'
```

---

## Módulo Académico (`ms-academico`) ⏳ pendiente

> Estos endpoints responden cuando `ms-academico` (Spring Boot) esté levantado.

### Matricular alumno — `POST /api/academico/students/enroll`

```bash
curl -X POST http://localhost/api/academico/students/enroll \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "studentId": "stu_001",
    "familyId": "fam_001",
    "gradeId": "grade_5A",
    "year": 2026
  }'
```

---

### Consultar alumno — `GET /api/academico/students/{studentId}`

```bash
curl http://localhost/api/academico/students/stu_001 \
  -H "Authorization: Bearer $TOKEN"
```

---

### Actualizar asistencia — `PATCH /api/academico/students/{studentId}/attendance`

```bash
curl -X PATCH http://localhost/api/academico/students/stu_001/attendance \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2026-05-30",
    "present": true,
    "justified": false
  }'
```

---

## Errores comunes

| HTTP | Mensaje | Causa |
|---|---|---|
| 401 `Token not provided` | Sin header `Authorization` |
| 401 `Token expired` | Token vencido — hacer `/auth/refresh` |
| 401 `Invalid credentials` | Email/password incorrectos |
| 422 | Campos de validación incorrectos |
| 500 `RPC timeout` | Microservicio no responde en 10 s |
| 500 `Connection refused` | RabbitMQ no disponible |

---

## Verificar estado del stack

```bash
# Contenedores activos
docker compose ps

# Logs en tiempo real
docker compose logs -f api-gateway
docker compose logs -f ms-ia

# Colas de RabbitMQ (Management UI)
open http://localhost:15672
# usuario: guest / contraseña: guest
```

---

## Script de prueba rápida (bash)

```bash
#!/bin/bash
BASE="http://localhost/api"

echo "=== 1. Registro ==="
RESP=$(curl -s -X POST $BASE/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"qa@edupay.com","password":"password123","password_confirmation":"password123"}')
TOKEN=$(echo $RESP | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
echo "Token: ${TOKEN:0:30}..."

echo ""
echo "=== 2. Me ==="
curl -s $BASE/auth/me -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

echo ""
echo "=== 3. Risk Score ==="
curl -s -X POST $BASE/ia/families/fam_001/risk-score \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "months_enrolled":12,"total_payments":10,"on_time_payments":8,
    "average_days_late":3.2,"max_consecutive_late":2,
    "has_paid_annual_ever":true,"preferred_payment_method_qr":false,
    "preferred_payment_method_stripe":true,"preferred_payment_method_blockchain":false,
    "uses_mobile_app":true,"has_discount":false,"is_after_carnaval":false
  }' | python3 -m json.tool

echo ""
echo "=== 4. Cluster ==="
curl -s -X POST $BASE/ia/families/fam_001/cluster \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"avg_monthly_income":3500,"num_children":2,"payment_regularity_score":0.85,"total_debt":1200}' \
  | python3 -m json.tool

echo ""
echo "=== 5. Payment Event ==="
curl -s -X POST $BASE/ia/payment-events \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"familyId":"fam_001","paymentId":"pay_001","amount":450,"currency":"BOB","method":"QR","paidAt":"2026-05-30T10:00:00Z","dueDate":"2026-05-28"}' \
  | python3 -m json.tool
```

Guardar como `test.sh`, ejecutar con `bash test.sh`.
