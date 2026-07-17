# EFD-Reinf Web (Laravel)

Sistema web para geração e transmissão de eventos EFD-Reinf conforme leiaute v2.1.2 da Receita Federal.

## Rodar localmente

```bash
git clone https://github.com/robertolinsfilho/reinf.git
cd reinf
cp .env.example .env
# Defina APP_SECRET e senhas de banco no .env
docker compose up -d --build
docker compose exec app php artisan key:generate
```

Acesse `http://localhost` (login: `admin@efdreinf.com.br` / senha: `admin123`)

## Rodar no GitHub Codespaces

1. Abrir o repo no GitHub → botão verde `< > Code` → aba **Codespaces** → **Create codespace on main**
2. Aguarde o build + `composer install`
3. Codespaces abre automaticamente a porta 80 no navegador
4. Login: `admin@efdreinf.com.br` / `admin123`

### Primeira execução (Codespaces)

Após o container subir, cole o arquivo `database/migrations/tabela4020_codigos.sql` no phpMyAdmin (porta 8080) para importar os 203 códigos da Tabela 4020 (se disponível).

### URLs no Codespaces

- **App:** porta 80 (URL pública gerada pelo Codespaces)
- **phpMyAdmin:** porta 8080 (`docker compose --profile tools up -d`)
- **MySQL:** porta 3306

## Estrutura

- PHP 8.2 + Laravel 11 + MySQL 8 + Nginx (Docker Compose)
- Eloquent Models + Repositories (PDO) + Services de domínio
- Eventos suportados: R-1000, R-1070, R-2010, R-2020, R-2060, R-2099, R-4010, R-4020, R-4099, R-9000
- Assinatura XMLDSig SHA-256 com certificado A1
- Transmissão REST para webservice da RFB (produção/homologação)

## Segurança para produção

Antes de subir em produção:

1. Troque a senha do admin (via /perfil)
2. Troque `APP_SECRET` e `APP_KEY` no `.env`
3. Troque `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD`
4. Configure certificado A1 em `/certificados`
5. Mude `REINF_TP_AMB=1` (produção) apenas quando estiver pronto
