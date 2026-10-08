'use strict';

const {
  SlashCommandBuilder,
  PermissionFlagsBits,
  ChannelType,
  EmbedBuilder,
} = require('discord.js');

/** Cargos na ordem (mais alto primeiro no array = criado depois, fica embaixo; Discord cria no topo. */
const ROLES = [
  { name: 'Membro', color: 0x9a9ab0, hoist: false },
  { name: 'Streamer', color: 0xe91e63, hoist: true },
  { name: 'Academy', color: 0x9b59b6, hoist: true },
  { name: 'Player', color: 0x2ecc71, hoist: true },
  { name: 'Coach', color: 0x3498db, hoist: true },
  { name: 'Staff', color: 0xe67e22, hoist: true },
  { name: 'Owner', color: 0xf5a623, hoist: true },
];

const STRUCTURE = [
  {
    name: '📢 INFORMAÇÕES',
    channels: [
      { name: 'regras', type: ChannelType.GuildText, staffOnlyWrite: true },
      { name: 'anuncios', type: ChannelType.GuildText, staffOnlyWrite: true },
      { name: 'resultados', type: ChannelType.GuildText, staffOnlyWrite: true },
      { name: 'redes-sociais', type: ChannelType.GuildText, staffOnlyWrite: true },
    ],
  },
  {
    name: '🎯 TIME',
    channels: [
      { name: 'lineup', type: ChannelType.GuildText },
      { name: 'scrims', type: ChannelType.GuildText },
      { name: 'treino', type: ChannelType.GuildText },
      { name: 'vod-review', type: ChannelType.GuildText },
      { name: 'staff', type: ChannelType.GuildText, privateTeam: true },
    ],
  },
  {
    name: '🎮 COMUNIDADE',
    channels: [
      { name: 'geral', type: ChannelType.GuildText },
      { name: 'clips', type: ChannelType.GuildText },
      { name: 'looking-for-game', type: ChannelType.GuildText },
      { name: 'bot-comandos', type: ChannelType.GuildText },
    ],
  },
  {
    name: '🔊 VOZ',
    channels: [
      { name: 'Lobby', type: ChannelType.GuildVoice },
      { name: 'Scrim 1', type: ChannelType.GuildVoice },
      { name: 'Scrim 2', type: ChannelType.GuildVoice },
      { name: 'Review', type: ChannelType.GuildVoice },
      { name: 'AFK', type: ChannelType.GuildVoice },
    ],
  },
];

const REGRAS_TEXTO = [
  '# Regras · MouseTrap Esports',
  '',
  '1. **Respeito** — sem toxicidade, preconceito ou assédio.',
  '2. **Sem spam** — flood, divulgação sem autorização e scam = ban.',
  '3. **Conteúdo** — NSFW, pirataria e cheats são proibidos.',
  '4. **Time** — assuntos internos do elenco ficam nos canais de time.',
  '5. **Scrims / treinos** — pontualidade e comunicação no `#scrims` / `#treino`.',
  '6. **Dúvidas** — use `#geral` ou chame a Staff.',
  '',
  '*Uma armadilha para gigantes* 🐭',
].join('\n');

function findRole(guild, name) {
  return guild.roles.cache.find((r) => r.name === name);
}

function findChannel(guild, name) {
  return guild.channels.cache.find((c) => c.name === name);
}

async function ensureRole(guild, def) {
  const existing = findRole(guild, def.name);
  if (existing) return { role: existing, created: false };
  const role = await guild.roles.create({
    name: def.name,
    color: def.color,
    hoist: def.hoist,
    mentionable: false,
    reason: 'MouseTrap /setup',
  });
  return { role, created: true };
}

async function ensureCategory(guild, name) {
  const existing = guild.channels.cache.find(
    (c) => c.type === ChannelType.GuildCategory && c.name === name
  );
  if (existing) return { channel: existing, created: false };
  const channel = await guild.channels.create({
    name,
    type: ChannelType.GuildCategory,
    reason: 'MouseTrap /setup',
  });
  return { channel, created: true };
}

function staffWriteOverwrites(guild, staffRole) {
  return [
    {
      id: guild.id,
      deny: [PermissionFlagsBits.SendMessages],
    },
    {
      id: staffRole.id,
      allow: [PermissionFlagsBits.SendMessages, PermissionFlagsBits.ViewChannel],
    },
  ];
}

function privateTeamOverwrites(guild, roles) {
  const allowIds = ['Owner', 'Staff', 'Coach', 'Player']
    .map((n) => roles[n])
    .filter(Boolean);

  return [
    { id: guild.id, deny: [PermissionFlagsBits.ViewChannel] },
    ...allowIds.map((r) => ({
      id: r.id,
      allow: [PermissionFlagsBits.ViewChannel, PermissionFlagsBits.SendMessages],
    })),
  ];
}

async function ensureChannel(guild, parent, def, roles) {
  const existing = findChannel(guild, def.name);
  if (existing) return { channel: existing, created: false };

  const options = {
    name: def.name,
    type: def.type,
    parent: parent.id,
    reason: 'MouseTrap /setup',
  };

  if (def.staffOnlyWrite && roles.Staff) {
    options.permissionOverwrites = staffWriteOverwrites(guild, roles.Staff);
  }
  if (def.privateTeam) {
    options.permissionOverwrites = privateTeamOverwrites(guild, roles);
  }

  const channel = await guild.channels.create(options);
  return { channel, created: true };
}

module.exports = {
  data: new SlashCommandBuilder()
    .setName('setup')
    .setDescription('Configura cargos, canais e regras do servidor MouseTrap')
    .addSubcommand((sub) =>
      sub
        .setName('servidor')
        .setDescription('Cria cargos + categorias + canais + posta regras (não apaga o que já existe)')
    )
    .addSubcommand((sub) =>
      sub
        .setName('regras')
        .setDescription('Reposta o texto de regras no #regras')
    )
    .setDefaultMemberPermissions(PermissionFlagsBits.Administrator),

  async execute(interaction) {
    const sub = interaction.options.getSubcommand();

    if (!interaction.guild) {
      return interaction.reply({ content: 'Use este comando dentro do servidor.', ephemeral: true });
    }

    if (sub === 'regras') {
      await interaction.deferReply({ ephemeral: true });
      const regras = findChannel(interaction.guild, 'regras');
      if (!regras || !regras.isTextBased()) {
        return interaction.editReply('Canal `#regras` não encontrado. Rode `/setup servidor` antes.');
      }
      await regras.send({ content: REGRAS_TEXTO });
      return interaction.editReply('Regras publicadas em #regras.');
    }

    await interaction.deferReply({ ephemeral: true });

    const me = interaction.guild.members.me;
    if (!me.permissions.has(PermissionFlagsBits.Administrator) &&
        !me.permissions.has([
          PermissionFlagsBits.ManageChannels,
          PermissionFlagsBits.ManageRoles,
        ])) {
      return interaction.editReply(
        'O bot precisa de permissão **Gerenciar Canais** e **Gerenciar Cargos** (ou Administrador).'
      );
    }

    const report = { rolesCreated: [], rolesSkipped: [], channelsCreated: [], channelsSkipped: [] };
    const roleMap = {};

    for (const def of ROLES) {
      const { role, created } = await ensureRole(interaction.guild, def);
      roleMap[def.name] = role;
      (created ? report.rolesCreated : report.rolesSkipped).push(def.name);
    }

    // Dono que rodou o comando ganha Owner se ainda não tiver
    const ownerRole = roleMap.Owner;
    if (ownerRole && !interaction.member.roles.cache.has(ownerRole.id)) {
      await interaction.member.roles.add(ownerRole).catch(() => {});
    }

    for (const cat of STRUCTURE) {
      const { channel: category } = await ensureCategory(interaction.guild, cat.name);
      for (const ch of cat.channels) {
        const { channel, created } = await ensureChannel(
          interaction.guild,
          category,
          ch,
          roleMap
        );
        (created ? report.channelsCreated : report.channelsSkipped).push(channel.name);
      }
    }

    const regrasCh = findChannel(interaction.guild, 'regras');
    if (regrasCh?.isTextBased()) {
      const already = (await regrasCh.messages.fetch({ limit: 10 }).catch(() => null));
      const hasRules = already?.some((m) => m.content.includes('Regras · MouseTrap'));
      if (!hasRules) {
        await regrasCh.send({ content: REGRAS_TEXTO });
      }
    }

    const embed = new EmbedBuilder()
      .setColor(0xf5a623)
      .setTitle('Setup MouseTrap concluído')
      .setDescription(
        'Estrutura aplicada **sem apagar** canais/cargos que já existiam com o mesmo nome.'
      )
      .addFields(
        {
          name: 'Cargos criados',
          value: report.rolesCreated.length ? report.rolesCreated.join(', ') : 'nenhum (já existiam)',
        },
        {
          name: 'Canais criados',
          value: report.channelsCreated.length
            ? report.channelsCreated.map((n) => `#${n}`).join(', ')
            : 'nenhum (já existiam)',
        }
      )
      .setFooter({ text: 'Arraste os cargos na ordem certa em Configurações → Cargos' });

    return interaction.editReply({ embeds: [embed] });
  },
};
