# MouseTrap Esports — Novo Site

Site institucional da organização de CS2 **MouseTrap Esports**, com painel administrativo próprio para publicar notícias, gerenciar o elenco de jogadores, cadastrar partidas/resultados e configurar a transmissão ao vivo — tudo sem precisar editar código ou usar FTP no dia a dia.

Construído em **PHP puro + MySQL**, compatível com hospedagem compartilhada básica (ex: Locaweb Hospedagem GO), sem depender de Node.js ou qualquer processo de build.

## Estrutura do projeto

```
mousetrap/
├── index.php, sobre.php, time.php, noticias.php, noticia.php, partidas.php, contato.php   → páginas públicas
├── includes/         → config, conexão com banco, funções auxiliares, header/footer do site
├── assets/           → CSS, JS e imagens (logo, placeholders)
├── uploads/          → imagens enviadas pelo painel (notícias, jogadores, adversários)
├── admin/            → painel administrativo protegido por login
│   ├── setup.php     → cria o primeiro usuário/senha (roda só uma vez)
│   ├── login.php / logout.php
│   ├── index.php     → dashboard
│   ├── noticias/      → CRUD de notícias
│   ├── jogadores/     → CRUD do elenco
│   ├── partidas/      → CRUD de partidas/resultados
│   └── configuracoes.php → frase de impacto, estatísticas, transmissão ao vivo, contato, redes sociais
└── database/schema.sql → script para criar as tabelas no MySQL
```

## Como testar localmente (Windows)

1. Instale o **Laragon** (mais simples) ou **XAMPP**, que já vêm com PHP + MySQL + Apache.
2. Copie esta pasta `mousetrap` para dentro da pasta `www` (Laragon) ou `htdocs` (XAMPP).
3. Abra o **phpMyAdmin** (vem com o Laragon/XAMPP), crie um banco chamado `mousetrap` e importe o arquivo `database/schema.sql`.
4. Confirme que `includes/config.php` está com `DB_HOST=localhost`, `DB_NAME=mousetrap`, `DB_USER=root`, `DB_PASS=` (vazio) — já vem assim por padrão.
5. Acesse `http://localhost/mousetrap/admin/setup.php` no navegador e crie seu usuário/senha do painel.
6. Faça login em `http://localhost/mousetrap/admin/login.php` e comece a cadastrar notícias, jogadores e partidas.
7. Veja o site público em `http://localhost/mousetrap/index.php`.

## Como publicar na Locaweb (produção)

O `includes/config.php` já detecta automaticamente se o site está rodando em `localhost` (usa as credenciais de desenvolvimento) ou no domínio real `mousetrap.com.br` (usa as credenciais de produção abaixo) — não precisa alternar manualmente.

1. **Banco de dados:** já criado no painel da Locaweb (`mousetrapdb` / servidor `mousetrapdb.mysql.dbaas.com.br`).
2. **Senha do banco:** defina/redefina em *Banco de Dados* no painel da Locaweb, e cole no lugar de `COLOQUE_AQUI_A_SENHA_DO_BANCO` dentro de `includes/config.php` (bloco de produção).
3. **Importar o schema:** clique em "Ir para phpMyAdmin" no painel da Locaweb e importe o arquivo `database/schema.sql` no banco `mousetrapdb`.
4. **Enviar os arquivos:** use o FTP do seu plano (dados de acesso no painel da Locaweb, em *FTP*) com um cliente como o FileZilla, ou o Gerenciador de Arquivos do próprio painel, e envie todo o conteúdo desta pasta para a raiz do domínio (geralmente a pasta `public_html` ou `www`).
5. **Certifique-se de enviar também as pastas `uploads/` vazias** (com permissão de escrita — em geral 755 já funciona na Locaweb).
6. Acesse `https://mousetrap.com.br/admin/setup.php` para criar o usuário do painel em produção.
7. Pelo painel, cadastre: frase de impacto, títulos/fundação, elenco, partidas e notícias.
8. Se quiser, delete ou renomeie `admin/setup.php` depois de criar o usuário (ele já se bloqueia automaticamente, mas por segurança extra você pode removê-lo).

## Funcionalidades do painel administrativo

- **Notícias:** título, categoria, resumo, conteúdo com editor de texto rico (imagens, negrito, links etc.), imagem de capa, status rascunho/publicado.
- **Jogadores:** nickname, nome real, função (IGL/AWPer/Rifler...), foto, redes sociais, ordem de exibição, ativo/inativo.
- **Partidas:** adversário, campeonato, data/hora, status agendada/finalizada, placar, logo do adversário, link da transmissão.
- **Configurações:** frase de impacto do hero, número de títulos, ano de fundação, transmissão ao vivo (Twitch ou YouTube — só colar o nome do canal ou ID do vídeo), e-mail/telefone/endereço de contato, links de redes sociais.

## Itens pendentes para deixar 100% pronto

- Substituir `assets/img/logo.svg` pelo arquivo oficial do logo (PNG/SVG em alta resolução).
- Adicionar uma foto real em `assets/img/hero.jpg` (imagem de destaque da home) e `assets/img/estrutura.jpg` (foto da sala/estrutura, usada na página Sobre).
- Cadastrar o elenco atual, próximas partidas e primeiras notícias pelo painel.
- Revisar textos institucionais na página Sobre (`sobre.php`), se quiser ajustar algo.

## Formulário de contato

A página `contato.php` usa a função `mail()` nativa do PHP para enviar as mensagens para o e-mail configurado em *Configurações > Contato*. Isso funciona out-of-the-box na maioria das hospedagens compartilhadas (incluindo Locaweb), mas se as mensagens não chegarem, pode ser necessário configurar um serviço de SMTP autenticado (ex: usar o e-mail profissional já incluso no seu plano Locaweb) — nesse caso, me avise que ajusto o envio para usar SMTP.

## Segurança

- Senhas de admin são armazenadas com hash (bcrypt via `password_hash`), nunca em texto puro.
- Formulários do painel usam proteção CSRF.
- A pasta `uploads/` bloqueia a execução de scripts, mesmo que um arquivo indevido seja enviado.
- As pastas `includes/` e `database/` são bloqueadas para acesso direto via navegador.
