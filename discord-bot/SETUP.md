# Discord MouseTrap Esports — CS2

Pasta do bot + guia de estrutura do servidor.

## 1) Estrutura do servidor (canais e cargos)

### Opção A — Automático com o bot (recomendado)

1. Convide o bot com permissão **Administrador** (ou Gerenciar Canais + Gerenciar Cargos)
2. No servidor, rode:

```
/setup servidor
```

Isso cria (se ainda não existir):
- Cargos: Owner, Staff, Coach, Player, Academy, Streamer, Membro
- Categorias e canais (info, time, comunidade, voz)
- Trava escrita em `#regras` / `#anuncios` / etc. (só Staff)
- Canal `#staff` privado
- Posta o texto de regras em `#regras`

Para só republicar as regras:

```
/setup regras
```

> O comando **não apaga** nada que já exista com o mesmo nome — só completa o que falta.

### Opção B — Template oficial do Discord (servidor novo)

1. Monte um servidor “modelo” (ou rode `/setup` nele)
2. Configurações do servidor → **Modelo** → Criar modelo
3. Copie o link `discord.new/...`
4. Abra o link → cria um **servidor novo** já com canais/cargos

Limitação: template **não** copia bots, mensagens nem membros — só estrutura. Em servidor que **já existe**, use `/setup` (Opção A).

### Opção C — Manual

### Cargos (do maior pro menor)

| Cargo | Cor sugerida | Quem recebe |
|---|---|---|
| Owner | Dourado `#f5a623` | dono |
| Staff | Laranja | admin/mods |
| Coach | Azul | coach |
| Player | Verde | lineup principal |
| Academy | Roxo | base / harry etc. |
| Streamer | Rosa | streamers |
| Membro | Cinza | comunidade |

**Dica Discord:** Configurações do servidor → Cargos → arraste nessa ordem (Owner no topo).

### Categorias e canais

```
📢 INFORMAÇÕES
  #regras            (só Staff escreve)
  #anuncios          (só Staff escreve)
  #resultados
  #redes-sociais

🎯 TIME
  #lineup
  #scrims
  #treino
  #vod-review
  #staff            (privado: Staff + Coach + Player)

🎮 COMUNIDADE
  #geral
  #clips
  #looking-for-game
  #bot-comandos     (manda o bot falar aqui)

🔊 VOZ
  Lobby
  Scrim 1
  Scrim 2
  Review
  AFK
```

### Permissões rápidas

1. Em `#regras` e `#anuncios`: @everyone → **Enviar mensagens = OFF**
2. Em `#staff`: negue ver canal para @everyone; permita Staff/Coach/Player
3. Ative **Verificação** (Configurações → Moderação) nível Médio
4. Crie um canal de boas-vindas e use Carl-bot/Dyno para auto-role `Membro`

### Bots extras (recomendados)

- **Carl-bot** ou **Dyno** — regras, reaction roles, moderação
- Este bot (**MouseTrap**) — Faceit / scrim

---

## 2) Bot MouseTrap (comandos CS2)

### Comandos

| Comando | Função |
|---|---|
| `/faceit [nick]` | Elo, level, WR, K/D, HS% |
| `/last [nick]` | Últimas partidas |
| `/vincular faceit nick:` | Liga Discord ↔ Faceit |
| `/vincular ver` | Mostra vínculo |
| `/vincular remover` | Remove vínculo |
| `/scrim` | Anuncia scrim (staff) |
| `/setup servidor` | Cria cargos + canais + regras |
| `/setup regras` | Republica texto em #regras |
| `/ajuda` | Lista comandos |

> Gamers Club não tem API pública estável como a Faceit. Por isso o bot usa **Faceit**. GC continua no site/manual.

### Passo a passo (criar o bot no Discord)

1. Acesse https://discord.com/developers/applications → **New Application** → nome `MouseTrap Bot`
2. Em **Bot** → Add Bot → copie o **Token**
3. Em **Bot** → Privileged Gateway Intents: pode deixar desligado (este bot só usa slash commands)
4. Em **OAuth2 → URL Generator**:
   - Scopes: `bot` + `applications.commands`
   - Bot Permissions: `Administrator` (mais fácil pro `/setup`) **ou** `Manage Channels` + `Manage Roles` + `Send Messages` + `Embed Links` + `Mention Everyone`
5. Abra a URL gerada e convide o bot pro servidor MouseTrap
6. Em **OAuth2 → General** copie o **Client ID**

### Faceit API Key

1. https://developers.faceit.com → crie app / pegue **Server Side API Key**
2. Guarde no `.env`

### Rodar o bot (no PC ou VPS)

```bash
cd discord-bot
copy .env.example .env
# edite o .env com TOKEN, CLIENT_ID, GUILD_ID, FACEIT_API_KEY

npm install
npm run register
npm start
```

O bot precisa ficar **ligado 24h**. A Locaweb compartilhada **não** roda Node. Opções baratas:

- Railway / Render / Oracle Cloud free
- Um PC/VPS pequeno sempre ligado

### Variáveis `.env`

```
DISCORD_TOKEN=...
DISCORD_CLIENT_ID=...
DISCORD_GUILD_ID=...          # ID do servidor (opcional, mas deixa comandos instantâneos)
FACEIT_API_KEY=...
```

Para pegar o **Guild ID**: Discord → Configurações → Avançado → Modo desenvolvedor ON → clique direito no servidor → Copiar ID.

---

## 3) Checklist rápido

- [ ] Criar cargos e canais acima
- [ ] Travar #anuncios / #regras
- [ ] Convidar Carl-bot (moderação)
- [ ] Criar app do bot + convidar
- [ ] Colocar keys no `.env`
- [ ] `npm install` → `npm run register` → `npm start`
- [ ] Testar `/ajuda` e `/faceit` no canal `#bot-comandos`
