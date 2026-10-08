'use strict';

const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('ajuda')
    .setDescription('Lista os comandos do bot MouseTrap'),

  async execute(interaction) {
    const embed = new EmbedBuilder()
      .setColor(0xf5a623)
      .setTitle('Comandos · MouseTrap Bot')
      .setDescription('Bot da org para CS2 / Faceit e organização do time.')
      .addFields(
        { name: '/faceit', value: 'Perfil Faceit (elo, level, WR, K/D, HS%)' },
        { name: '/last', value: 'Últimas partidas Faceit' },
        { name: '/vincular faceit', value: 'Liga seu Discord ao nick Faceit' },
        { name: '/vincular ver', value: 'Mostra o nick vinculado' },
        { name: '/vincular remover', value: 'Remove o vínculo' },
        { name: '/scrim', value: 'Anuncia scrim/treino (staff)' },
        { name: '/setup servidor', value: 'Cria cargos, canais e regras (admin)' },
        { name: '/setup regras', value: 'Republica as regras no #regras' },
        { name: '/ajuda', value: 'Esta mensagem' }
      )
      .setFooter({ text: 'Uma armadilha para gigantes' });

    await interaction.reply({ embeds: [embed], ephemeral: true });
  },
};
