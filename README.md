# EFD REINF – Sistema Web PHP

Sistema web para geração e gestão de arquivos **EFD REINF** (Escrituração Fiscal Digital de Retenções e Outras Informações Fiscais), construído com **PHP 8.2**, **MySQL 8** e **Docker**.

---

## 🚀 Como Iniciar

### Pré-requisitos
- Docker + Docker Compose instalados

### 1. Clone e configure

```bash
git clone <seu-repo> efd-reinf
cd efd-reinf
cp .env.example .env
# Edite o .env se necessário
```

### 2. Suba os containers

```bash
docker-compose up -d --build
```

### 3. Acesse

| URL | Serviço |
|-----|---------|
| http://localhost | Sistema EFD REINF |
| http://localhost:8080 | phpMyAdmin |

### 4. Login padrão

| Campo | Valor |
|-------|-------|
| E-mail | admin@efdreinf.com.br |
| Senha | admin123 |

> ⚠️ Altere a senha após o primeiro acesso!

---

## 📂 Estrutura do Projeto

```
efd-reinf/
├── docker/
│   ├── Dockerfile          # PHP 8.2 FPM
│   └── nginx.conf          # Configuração Nginx
├── public/
│   ├── index.php           # Front controller
│   ├── css/app.css
│   └── uploads/            # Uploads e XMLs gerados
├── src/
│   ├── Controllers/        # Controllers da aplicação
│   ├── Models/Database.php # Singleton PDO
│   ├── Services/
│   │   ├── ImportacaoService.php   # Leitura do Excel
│   │   └── GeracaoXmlService.php   # Geração do XML REINF
│   └── Views/              # Templates PHP
├── database/
│   └── migrations/init.sql # Schema completo do banco
├── config/app.php
├── composer.json
└── docker-compose.yml
```

---

## 📋 Funcionalidades

### ✅ Implementadas
- **Autenticação** com sessão PHP e hash bcrypt
- **Contribuintes** – CRUD completo
- **Competências** – Períodos de apuração por contribuinte
- **Evento R-2010** – Retenções INSS Contratados (entrada manual + importação Excel)
- **Evento R-2020** – Retenções INSS Contratantes
- **Evento R-2060** – CPRB com cálculo automático
- **Importação Excel** – Leitura de .xlsx/.xls com PhpSpreadsheet
- **Geração XML** – Arquivos R-1000, R-2010, R-2020, R-2050, R-2055, R-2060, R-9000
- **Download** dos XMLs gerados
- **Gestão de Usuários** (admin)
- **Dashboard** com totalizadores

### 🔜 Para implementar (próximos passos)
- Validação de CNPJ/CPF
- Exportação de relatórios em Excel/PDF
- Envio por certificado digital A1 via webservice SPED
- Histórico de transmissões
- Múltiplos estabelecimentos por contribuinte

---

## 📊 Layout das Planilhas Excel

### R-2010 (Retenções INSS Contratados)
| Coluna | Conteúdo |
|--------|----------|
| A | CNPJ do Prestador |
| B | Razão Social |
| C | Nº do Documento |
| D | Data de Emissão (DD/MM/AAAA) |
| E | Valor Bruto |
| F | Valor Retenção |
| G | Valor SENAR |

### R-2020 (Retenções INSS Contratantes)
| Coluna | Conteúdo |
|--------|----------|
| A | CNPJ do Tomador |
| B | Razão Social |
| C | Nº do Documento |
| D | Data de Emissão |
| E | Valor Bruto |
| F | Valor Retenção |

### R-2060 (CPRB)
| Coluna | Conteúdo |
|--------|----------|
| A | CNAE |
| B | Receita Bruta |
| C | Exclusões |
| D | Alíquota (%) |

> Linha 1 é o cabeçalho (ignorada). Dados a partir da linha 2.

---

## ⚙️ Configuração de Produção

1. Edite `.env` com suas credenciais reais
2. Altere `tpAmb` de `2` (homologação) para `1` (produção) no `GeracaoXmlService.php`
3. Configure HTTPS no nginx
4. Altere `APP_SECRET` para uma string aleatória segura

---

## 🐳 Comandos úteis

```bash
# Subir
docker-compose up -d

# Ver logs
docker-compose logs -f app

# Acessar container PHP
docker-compose exec app bash

# Reinstalar composer
docker-compose exec app composer install

# Parar
docker-compose down

# Parar e remover dados do banco
docker-compose down -v
```

---

## 📄 Licença

Desenvolvido para uso interno. Adapte conforme necessário.
