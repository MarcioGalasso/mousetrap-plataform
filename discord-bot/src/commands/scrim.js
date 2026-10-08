'use strict';

const { SlashCommandBuilder, EmbedBuilder, PermissionFlagsBits } = require('discord.js');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('scrim')
    .setDescription('Anuncia um scrim / treino do time')
    .addStringOption((opt) =>
      opt.setName('adversario').setDescription('Nome do adversário').setRequired(true)
    )
    .addStringOption((opt) =>
      opt.setName('horario').setDescription('Horário (ex: hoje 21h)').setRequired(true)
    )
    .addStringOption((opt) =>
      opt.setName('formato').setDescription('Formato').addChoices(
        { name: 'MD1', value: 'MD1' },
        { name: 'MD2', value: 'MD2' },
        { name: 'MD3', value: 'MD3' },
        { name: 'Treino', value: 'Treino' }
      )
    )
    .addStringOption((opt) =>
      opt.setName('obs').setDescription('Observações')
    )
    .setDefaultMemberPermissions(PermissionFlagsBits.ManageEvents),

  async execute(interaction) {
    const adversario = interaction.options.getString('adversario', true);
    const horario = interaction.options.getString('horario', true);
    const formato = interaction.options.getString('formato') || 'MD1';
    const obs = interaction.options.getString('obs');

    const embed = new EmbedBuilder()
      .setColor(0xf5a623)
      .setTitle('🎯 Scrim agendado')
      .addFields(
        { name: 'Adversário', value: adversario, inline: true },
        { name: 'Horário', value: horario, inline: true },
        { name: 'Formato', value: formato, inline: true }
      )
      .setFooter({ text: `Por ${interaction.user.tag} · MouseTrap Esports` })
      .setTimestamp();

    if (obs) embed.addFields({ name: 'Obs', value: obs });

    await interaction.reply({
      content: '@here scrim marcado — confirmem presença no canal de voz.',
      embeds: [embed],
      allowedMentions: { parse: ['everyone'] },
    });
  },
};
