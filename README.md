# Tokenization Vault System 🔐

A comprehensive data tokenization and vault management system designed for enterprise-grade security, compliance, and scalability.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel)
![React](https://img.shields.io/badge/React-18+-61DAFB?logo=react)
![TypeScript](https://img.shields.io/badge/TypeScript-5+-3178C6?logo=typescript)

## 🎯 What is Tokenization?

**Tokenization** is the process of replacing sensitive data with non-sensitive placeholders called "tokens". This allows organizations to reduce their security footprint while maintaining operational functionality.

### Example:
```
Sensitive Data:    4111-1111-1111-1111 (Credit Card)
Tokenized:         TOK_CC_abc123xyz789 (Safe Token)
```

**Benefits:**
- 🛡️ **Security**: Sensitive data stored in secure vault only
- 📋 **Compliance**: Meet PCI DSS, HIPAA, GDPR requirements
- 🔄 **Functionality**: Maintain business operations with tokens
- 📊 **Analytics**: Process data without exposing sensitive information

## 🚀 Features

### Core Tokenization
- **🔐 Data Tokenization**: Convert sensitive data to secure tokens
- **🔓 Detokenization**: Retrieve original data when authorized
- **🔍 Token Search**: Find tokens without exposing sensitive data
- **📦 Bulk Operations**: Process large datasets efficiently

### Vault Management
- **🗄️ Multiple Vaults**: Organize data by domain, compliance, or business unit
- **🔑 Key Rotation**: Automated encryption key management
- **📊 Statistics**: Vault usage and performance metrics
- **🔒 Access Control**: Fine-grained permission system

### Security & Compliance
- **🛡️ AES-256 Encryption**: Military-grade data protection
- **📝 Audit Logging**: Complete operation history
- **⚡ Rate Limiting**: Protection against abuse
- **🌐 CORS Support**: Secure cross-origin requests
- **🔐 API Key Authentication**: Secure API access

### Monitoring & Analytics
- **📈 Real-time Metrics**: Performance monitoring
- **🚨 Security Alerts**: Suspicious activity detection
- **📊 Usage Reports**: Compliance reporting
- **🔍 Request Tracing**: Full request lifecycle tracking

## 🏗️ Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   React Frontend │    │   Laravel API    │    │  Secure Vault   │
│                 │    │                  │    │                 │
│  • Login UI     │◄──►│  • Authentication│◄──►│  • Encrypted    │
│  • Token Mgmt   │    │  • Rate Limiting │    │    Data Storage │
│  • Analytics    │    │  • Audit Logs    │    │  • Key Mgmt     │
│  • Admin Panel  │    │  • CORS Handling │    │  • Backup       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │
                                ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│     MySQL 8.0   │    │    RabbitMQ      │    │    Redis 7      │
│                 │    │                  │    │                 │
│  • Vault Data   │    │  • Async Jobs    │    │  • Cache Layer  │
│  • Audit Logs   │    │  • Key Rotation  │    │  • Sessions     │
│  • User Mgmt    │    │  • Notifications │    │  • Rate Limits  │
│  • Tokens       │    │  • Bulk Ops      │    │  • Temp Data    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Technology Stack

**Backend (Laravel 11)**
- **Framework**: Laravel 11 with PHP 8.1+
- **Database**: MySQL 8.0 with optimized configuration
- **Cache**: Redis 7 for session and cache management
- **Queue**: RabbitMQ for background job processing and messaging
- **Security**: Custom middleware for API auth, rate limiting

**Frontend (React 18)**
- **Framework**: React 18 with TypeScript
- **Build Tool**: Vite for fast development
- **Styling**: Tailwind CSS for modern UI
- **State Management**: React hooks and context
- **HTTP Client**: Axios with interceptors

**Infrastructure (Docker)**
- **MySQL**: Persistent data storage with custom configuration
- **Redis**: High-performance caching and session storage
- **RabbitMQ**: Reliable message queuing with management UI
- **Docker Compose**: Complete development environment

## 🛠️ Installation

### Prerequisites

- **Docker & Docker Compose** (recommended)
- **PHP 8.1+** with extensions: mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, ctype, json, bcmath
- **Node.js 18+** and npm/yarn
- **Composer** for PHP dependencies

### Docker Setup (Recommended) 🐳

The easiest way to get started is using Docker:

```bash
# Clone the repository
git clone https://github.com/your-username/tokenization-vault-system.git
cd tokenization-vault-system

# Start all services
docker-compose up -d


# Setup backend
cd backend
composer install
cp .env.example .env

# Update .env with Docker services
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3309
DB_DATABASE=vault_system
DB_USERNAME=vault_user
DB_PASSWORD=vault_pass_2024

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=vault_admin
RABBITMQ_PASSWORD=vault_rabbit_2024

# Generate application key and run migrations
php artisan key:generate
php artisan migrate
php artisan vault:setup

# Setup frontend
cd ../frontend
npm install
cp .env.example .env.local

# Start development servers
# Terminal 1: Backend
cd backend && php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Frontend  
cd frontend && npm run dev

# Terminal 3: Queue Worker (for background jobs)
cd backend && php artisan queue:work
```

**🎉 Services running:**
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **MySQL**: localhost:3309
- **Redis**: localhost:6379
- **RabbitMQ Management**: http://localhost:15672

### Docker Commands

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# View logs
docker-compose logs -f [service_name]

# Reset all data (⚠️ DESTRUCTIVE)
docker-compose down -v
docker volume prune -f

# Access MySQL
docker exec -it vault_mysql mysql -u vault_user -p vault_system

# Access Redis
docker exec -it vault_redis redis-cli

# Check service status
docker-compose ps
```

### Quick Start with Docker 🐳

```bash
# Clone the repository
git clone https://github.com/your-username/tokenization-vault-system.git
cd tokenization-vault-system

# Start all services (MySQL, Redis, RabbitMQ)
docker-compose up -d

# Install backend dependencies
cd backend
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Setup vault system
php artisan vault:setup

# Install frontend dependencies
cd ../frontend
npm install

# Start development
npm run dev
```

**🎉 That's it! Your application is now running:**
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **RabbitMQ Management**: http://localhost:15672 (vault_admin / vault_rabbit_2024)

### Background Jobs & Queues

The system uses **RabbitMQ** for handling background operations:

#### Queue Workers
```bash
# Start queue worker
php artisan queue:work

# Start multiple workers
php artisan queue:work --queue=high,default,low

# Process specific queue
php artisan queue:work --queue=tokenization

# Run as daemon (production)
php artisan queue:work --daemon --sleep=3 --tries=3
```

#### Background Jobs
- **🔄 Bulk Tokenization**: Large dataset processing
- **🔑 Key Rotation**: Automated encryption key updates
- **📊 Analytics**: Usage statistics computation
- **📧 Notifications**: Security alerts and reports
- **🗑️ Data Cleanup**: Expired token removal
- **📋 Audit Processing**: Log aggregation and analysis

#### RabbitMQ Management
Access the management interface at http://localhost:15672
- **Username**: vault_admin
- **Password**: vault_rabbit_2024

**Monitor:**
- Queue depths and throughput
- Message rates and processing times
- Failed job tracking
- Worker performance metrics

## 📡 API Documentation

### Authentication

All API endpoints (except health checks) require API key authentication:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     https://api.your-domain.com/api/v1/tokenize
```

### Core Endpoints

#### Health Check
```bash
GET /api/health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "1.0.0",
  "environment": "production"
}
```

#### Tokenize Data
```bash
POST /api/v1/tokenize
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY

{
  "vault": "payment_data",
  "data": {
    "credit_card": "4111-1111-1111-1111",
    "email": "customer@example.com"
  },
  "metadata": {
    "customer_id": "CUST_12345",
    "transaction_type": "purchase"
  }
}
```

**Response:**
```json
{
  "success": true,
  "tokens": {
    "credit_card": "TOK_CC_a1b2c3d4e5f6",
    "email": "TOK_EMAIL_x7y8z9w6v5u4"
  },
  "request_id": "req_abc123def456",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

#### Detokenize Data
```bash
POST /api/v1/detokenize
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY

{
  "vault": "payment_data",
  "tokens": ["TOK_CC_a1b2c3d4e5f6", "TOK_EMAIL_x7y8z9w6v5u4"]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "TOK_CC_a1b2c3d4e5f6": "4111-1111-1111-1111",
    "TOK_EMAIL_x7y8z9w6v5u4": "customer@example.com"
  },
  "request_id": "req_def456ghi789"
}
```

#### Search Tokens
```bash
POST /api/v1/search
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY

{
  "vault": "payment_data",
  "criteria": {
    "metadata.customer_id": "CUST_12345",
    "created_after": "2024-01-01T00:00:00Z"
  },
  "limit": 50
}
```

#### Bulk Tokenize
```bash
POST /api/v1/bulk-tokenize
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY

{
  "vault": "customer_data",
  "batch": [
    {
      "id": "record_1",
      "data": {"email": "user1@example.com", "phone": "+1234567890"}
    },
    {
      "id": "record_2", 
      "data": {"email": "user2@example.com", "phone": "+0987654321"}
    }
  ]
}
```

### Vault Management

#### List Vaults
```bash
GET /api/v1/vaults
```

#### Create Vault
```bash
POST /api/v1/vaults
Content-Type: application/json

{
  "name": "customer_pii",
  "description": "Customer personally identifiable information",
  "encryption_algorithm": "aes-256-gcm",
  "access_policy": {
    "allowed_operations": ["tokenize", "detokenize", "search"],
    "data_retention_days": 2555,
    "key_rotation_days": 90
  }
}
```

#### Vault Statistics
```bash
GET /api/v1/vaults/customer_pii/statistics
```

**Response:**
```json
{
  "vault": "customer_pii",
  "token_count": 125000,
  "storage_used": "2.5GB",
  "operations_24h": {
    "tokenize": 1250,
    "detokenize": 890,
    "search": 340
  },
  "last_key_rotation": "2024-01-01T00:00:00Z",
  "next_key_rotation": "2024-04-01T00:00:00Z"
}
```

### Audit & Monitoring

#### Audit Logs
```bash
GET /api/v1/audit/logs?vault=customer_pii&limit=100&after=2024-01-01
```

#### Security Alerts
```bash
GET /api/v1/audit/alerts?severity=high&limit=50
```

## 🔒 Security

### Encryption
- **Algorithm**: AES-256-GCM for data encryption
- **Key Management**: Automated key rotation every 90 days
- **Initialization Vectors**: Unique IV for each encryption operation
- **Key Derivation**: PBKDF2 with 100,000 iterations

### Access Control
- **API Keys**: Unique keys per application/environment
- **Rate Limiting**: Configurable per-key limits
- **IP Whitelisting**: Optional IP-based access control
- **Audit Trail**: Complete operation logging

### Compliance Features
- **PCI DSS**: Secure card data tokenization
- **HIPAA**: Healthcare data protection
- **GDPR**: Right to erasure support
- **SOX**: Financial data compliance

### Best Practices

1. **Never log sensitive data** - Only tokens in logs
2. **Rotate API keys regularly** - Default 30 days
3. **Monitor for anomalies** - Set up alerts
4. **Backup encrypted** - Regular vault backups
5. **Network security** - Use HTTPS/TLS only

## 🧪 Testing

### Backend Tests
```bash
cd backend

# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

### Frontend Tests
```bash
cd frontend

# Run unit tests
npm test

# Run with coverage
npm run test:coverage

# Run end-to-end tests
npm run test:e2e
```

### API Testing
```bash
# Health check
curl http://localhost:8000/api/health

# Test tokenization (replace API_KEY)
curl -X POST http://localhost:8000/api/v1/tokenize \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"vault":"test","data":{"email":"test@example.com"}}'
```

## 📊 Monitoring

### Metrics Collection
- **Request Volume**: Operations per second/minute/hour
- **Response Times**: P50, P95, P99 latencies
- **Error Rates**: Failed operations by type
- **Vault Usage**: Storage utilization per vault
- **Queue Metrics**: RabbitMQ message rates and queue depths
- **Database Performance**: MySQL connection pools and query times
- **Cache Hit Rates**: Redis performance metrics

### Health Checks
- **Database Connectivity**: MySQL connection pool status
- **Redis Availability**: Cache and session storage
- **RabbitMQ Status**: Message queue availability
- **Encryption Service**: Key availability
- **Memory Usage**: Application resource consumption
- **Queue Workers**: Background job processing status

### Service Monitoring
```bash
# Check all services health
curl http://localhost:8000/api/health

# MySQL health
docker exec vault_mysql mysqladmin ping

# Redis health  
docker exec vault_redis redis-cli ping

# RabbitMQ health
docker exec vault_rabbitmq rabbitmqctl node_health_check

# Queue worker status
php artisan queue:monitor

# System metrics
docker stats vault_mysql vault_redis vault_rabbitmq
```

### Alerting
- **High Error Rate**: >1% failed requests
- **Slow Response**: P95 latency >500ms
- **Key Rotation Due**: 7 days before expiry
- **Storage Threshold**: >80% vault capacity
- **Queue Backup**: >1000 pending messages
- **Database Lag**: MySQL replication delay >30s
- **Memory Usage**: >85% container memory limit

## 🤝 Contributing

### Development Setup
```bash
# Fork and clone the repository
git clone https://github.com/your-username/tokenization-vault-system.git

# Create feature branch
git checkout -b feature/your-feature-name

# Install dependencies
cd backend && composer install
cd ../frontend && npm install

# Make your changes and test
php artisan test
npm test

# Commit and push
git commit -m "Add your feature description"
git push origin feature/your-feature-name
```

### Code Standards
- **PHP**: PSR-12 coding standard
- **JavaScript/TypeScript**: ESLint + Prettier
- **Documentation**: Update README for new features
- **Tests**: Maintain >90% test coverage

### Pull Request Process
1. Ensure all tests pass
2. Update documentation
3. Add changelog entry
4. Request review from maintainers

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Getting Help
- **Documentation**: [Full API Documentation](docs/api.md)
- **Examples**: [Integration Examples](examples/)
- **Issues**: [GitHub Issues](https://github.com/your-username/tokenization-vault-system/issues)

### Commercial Support
For enterprise deployments, compliance consulting, or custom integrations:
- Email: support@your-domain.com
- Website: https://your-domain.com/enterprise

## 🗂️ Related Projects

- **[Vault CLI](https://github.com/your-username/vault-cli)**: Command-line interface for vault management
- **[Vault Monitoring](https://github.com/your-username/vault-monitoring)**: Grafana dashboards and Prometheus metrics
- **[SDK Collection](https://github.com/your-username/vault-sdks)**: Client libraries for multiple languages

---

**Built with ❤️ for secure data tokenization**