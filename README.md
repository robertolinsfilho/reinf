# EFD-Reinf Web

Sistema web para geração e transmissão de eventos EFD-Reinf conforme leiaute v2.1.2 da Receita Federal.

## Rodar localmente

```bash
git clone https://github.com/robertolinsfilho/reinf.git
cd reinf
docker compose up -d --build
```

Acesse `http://localhost` (login: `admin@efdreinf.com.br` / senha: `admin123`)

## Rodar no GitHub Codespaces

1. Abrir o repo no GitHub → botão verde `< > Code` → aba **Codespaces** → **Create codespace on main**
2. Aguarde ~3 minutos (build + composer install)
3. Codespaces abre automaticamente a porta 80 no navegador
4. Login: `admin@efdreinf.com.br` / `admin123`

### Primeira execução (Codespaces)

Após o container subir, cole o arquivo `database/migrations/tabela4020_codigos.sql` no phpMyAdmin (porta 8080) para importar os 203 códigos da Tabela 4020.

### URLs no Codespaces

- **App:** porta 80 (URL pública gerada pelo Codespaces)
- **phpMyAdmin:** porta 8080
- **MySQL:** porta 3306

## Estrutura

- PHP 8.2 + MySQL 8 + Nginx (Docker Compose)
- Arquitetura MVC com Repository pattern
- Eventos suportados: R-1000, R-1070, R-2010, R-2020, R-2060, R-2099, R-4010, R-4020, R-4099, R-9000
- Assinatura XMLDSig SHA-256 com certificado A1
- Transmissão REST para webservice da RFB (produção/homologação)

## Segurança para produção

Antes de subir em produção:

1. Troque a senha do admin (via /perfil)
2. Troque `APP_SECRET` no `.env`
3. Troque `DB_PASS` e `MYSQL_ROOT_PASSWORD` no `docker-compose.yml`
4. Configure certificado A1 em `/certificados`
5. Mude `REINF_TP_AMB=1` (produção) apenas quando estiver pronto