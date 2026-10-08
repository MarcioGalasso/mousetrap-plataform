'use strict';

const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const { getPlayerByNickname, getPlayerStats, pickCsGame } = require('../utils/faceit');
const { getFaceitNick } = require('../utils/links');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('faceit')
    .setDescription('Mostra o perfil Faceit CS2 de um jogador')
    .addStringOption((opt) =>
      opt.setName('nick').setDescription('Nickname Faceit (se vazio, usa o vínculo do Discord)')
    )
    .addUserOption((opt) =>
      opt.setName('usuario').setDescription('Membro do Discord já vinculado')
    ),

  async execute(interaction) {
    await interaction.deferReply();

    let nick = interaction.options.getString('nick');
    const user = interaction.options.getUser('usuario');

    if (!nick && user) nick = getFaceitNick(user.id);
    if (!nick) nick = getFaceitNick(interaction.user.id);

    if (!nick) {
      return interaction.editReply(
        'Informe um nick Faceit ou vincule o seu com `/vincular nick:SEU_NICK`.'
      );
    }

    try {
      const player = await getPlayerByNickname(nick);
      if (!player) {
        return interaction.editReply(`Jogador \`${nick}\` não encontrado na Faceit.`);
      }

      const game = pickCsGame(player);
      const stats = await getPlayerStats(player.player_id, game?.key || 'cs2');
      const lifetime = stats?.lifetime || {};

      const elo = game?.data?.faceit_elo ?? '—';
      const level = game?.data?.skill_level ?? '—';
      const region = game?.data?.region ?? '—';

      const embed = new EmbedBuilder()
        .setColor(0xf5a623)
        .setAuthor({
          name: 'MouseTrap Esports · Faceit',
          iconURL: interaction.client.user.displayAvatarURL(),
        })
        .setTitle(player.nickname)
        .setURL(`https://www.faceit.com/pt/players/${encodeURIComponent(player.nickname)}`)
        .setThumbnail(player.avatar || null)
        .addFields(
          { name: 'Elo', value: String(elo), inline: true },
          { name: 'Level', value: String(level), inline: true },
          { name: 'Jogo', value: (game?.key || 'cs2').toUpperCase(), inline: true },
          { name: 'Partidas', value: String(lifetime['Matches'] || lifetime.Matches || '—'), inline: true },
          { name: 'Win Rate', value: String(lifetime['Win Rate %'] || lifetime['Win Rate'] || '—'), inline: true },
          { name: 'HS %', value: String(lifetime['Average Headshots %'] || lifetime['Headshots %'] || '—'), inline: true },
          { name: 'K/D', value: String(lifetime['Average K/D Ratio'] || lifetime['K/D Ratio'] || '—'), inline: true },
          { name: 'Região', value: String(region).toUpperCase(), inline: true },
          { name: 'País', value: player.country ? player.country.toUpperCase() : '—', inline: true }
        )
        .setFooter({ text: 'Uma armadilha para gigantes' })
        .setTimestamp();

      return interaction.editReply({ embeds: [embed] });
    } catch (err) {
      console.error(err);
      return interaction.editReply(`Erro ao consultar Faceit: ${err.message}`);
    }
  },
};
