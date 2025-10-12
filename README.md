# Convenia Collaborators API

O projeto tem como objetivo facilitar a gestão de colaboradores por meio de uma interface que oferece operações básicas de CRUD (Create, Read, Update, Delete) e importação em massa de funcionários, via CSV.

Além das rotas padrão para criação, edição, visualização e exclusão de registros, o sistema conta com uma rota dedicada para importações em lote de arquivos CSV. Essa rota processa e valida os dados de forma assíncrona utilizando Jobs do Laravel, permitindo o tratamento eficiente de grandes volumes.

Utilizei as melhores práticas da comunidade Laravel e utilizei algumas das últimas features do Laravel 12. Deixei alguns comentários pelo projeto pra explicar um pouco melhor meus pensamentos. Também deixei algumas referências no código.

## Requisitos da API
Você deve desenvolver uma API de colaboradores considerando:
- API desenvolvida em Laravel.
- Testes automatizados implementados.
- Padrão REST.
- Sistema com login de usuário.
- Autenticação por token (JWT ou Passport).
- O usuário autenticado representa um Gestor e deve visualizar apenas os colaboradores que cadastrou, sem permissão para alterar colaboradores de outros gestores.
- Ao inserir um colaborador, ele automaticamente “pertence” ao usuário logado.

## Endpoints Esperados
- Inserir colaborador: cria um novo colaborador.
- Listar colaboradores: lista os colaboradores do gestor logado.
- Editar colaborador: atualiza dados de um colaborador existente.
- Deletar colaborador: remove um colaborador.
- Upload em massa (CSV): processa um CSV de colaboradores (arquivo fornecido com o teste).
- Notificação por e-mail após processamento do CSV para o gestor logado com a mensagem: "Processamento realizado com sucesso".

## Instalação

### Pré-requisitos
- Docker e PHP 8.2+

---

### Passo a passo

1. Instalar dependências do PHP (Sail precisa delas pra rodar o binário sail)
```sh
composer install
```

2. Copiar o .env base
```sh
cp .env.example .env
```

3. Ajuste portas no `.env` se necessário:
```env
APP_PORT=8000
FORWARD_DB_PORT=5450
FORWARD_REDIS_PORT=6385
```

3. Subir os containers do Sail
```sh
./vendor/bin/sail up -d
```

4. Gere a app key
```sh
./vendor/bin/sail artisan key:generate
```

5. Gere o segredo JWT
```sh
./vendor/bin/sail artisan jwt:secret
```
6. Rode migrações e seeds
```sh
./vendor/bin/sail artisan migrate --seed
```
7. Gere a documentação Swagger (opcional)
```sh
./vendor/bin/sail artisan l5-swagger:generate
```

8. Extra: Configure o alias no seu shell
> https://laravel.com/docs/12.x/sail#configuring-a-shell-alias
---

### URLs úteis
- API base: [http://localhost:8000](http://localhost:8000)
- Swagger UI: [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)
- Mailpit (SMTP/Dashboard): [http://localhost:8025](http://localhost:8025)

---

### Filas (processamento assíncrono)

Para processar o upload em massa (CSV) e envio de e-mails, rode o worker:

```php
sail artisan queue:work
```

## Rotas
Veja a seção [Postman](#postman) para as rotas completas, ou o [Swagger](http://localhost:8000/api/documentation)
- `POST /api/v1/auth/login` — autenticação (JWT)
- `POST /api/v1/auth/refresh` — refresh do token
- `GET /api/v1/auth/me` — perfil do usuário autenticado
- `POST /api/v1/auth/logout` — logout
- `GET /api/v1/collaborators` — listar colaboradores do gestor logado
- `POST /api/v1/collaborators` — criar colaborador
- `GET /api/v1/collaborators/{id}` — detalhar colaborador
- `PUT/PATCH /api/v1/collaborators/{id}` — atualizar colaborador
- `DELETE /api/v1/collaborators/{id}` — remover colaborador
- `POST /api/v1/collaborators/import` — upload em massa via CSV (campo `file`)

## Libs
- JWT (`tymon/jwt-auth`) para autenticação. Achei o Passport overkill.
- L5 Swagger (`darkaonline/l5-swagger`) para documentação da API.
- Redis (cache/sessão/filas) e Mailpit (e-mails de desenvolvimento).
- Larastan (PHPStan nível 10) e Pint (CS fixer) para qualidade de código.
- Pest para escrita de testes. Dei publish nas configurações do Sail pra adicionar suporte ao xDebug, pra conseguir executar corretamente o --coverage.

## Testes e Coverage
- Rodar testes: `sail pest --coverage --parallel`
- Resultado atual: 14 testes, 54 assertions, ~81% coverage.
- Pra aumentar a cobertura, precisaria escrever mais testes unitários para os arquivos mostrados no relatório.

## Qualidade de Código
- Pint (CS): `sail composer pint` (checagem) e `sail composer pint:fix` (ajuste)
- PHPStan (nível 10): `sail composer phpstan`

## Seed de Usuários
- `admin@example.com` / `a1b2c3d4e5`
- `manager@example.com` / `a1b2c3d4e5`

Após login, use o token JWT retornado no header `Authorization: Bearer <TOKEN>` para acessar as rotas autenticadas.

## Postman
- Coleção: importe `convenia.postman_collection.json` (raiz do projeto).
- Ambiente (recomendo): crie um ambiente, selecione e defina as variáveis:
  - `base_url`: `http://localhost:8000`
  - `token`: deixe em branco (será preenchido automaticamente após login/refresh)
- Obtenha o token: execute a requisição "login" na coleção. Os scripts de teste salvam `access_token` em `pm.environment.set("token", ...)`.
- Use as rotas autenticadas: as requisições já usam Bearer Token `{{token}}` automaticamente.
- Import CSV: na requisição `collaborators/import`, selecione o arquivo no campo `file` (ex.: `employees.csv` na raiz do projeto). Ajuste o caminho do arquivo conforme seu sistema.

## Melhorias
- A depender do frontend, é bom usarmos HTTP-Only Cookies na autenticação.
- Talvez seja legal criar um Command pra importação de arquivos.
- Instalação de Horizon e Telescope pra pra ajudar no debug dos jobs e requisições.
- Setei o limite do CSV como 20mb, mas poderíamos usar chunk-uploads via TUS Protocol ou S3 pre-signed pra arquivos maiores.
- Pensar em alguma forma de adicionar idempotência no upload pra evitar reprocessamento do mesmo arquivo
- Adicionar observabilidade nessa aplicação, de preferência via OTEL pra evitar vendor lock.