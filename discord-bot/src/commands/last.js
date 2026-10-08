'use strict';

const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const { getPlayerByNickname, getRecentMatches, pickCsGame } = require('../utils/faceit');
const { getFaceitNick } = require('../utils/links');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('last')
    .setDescription('Mostra as últimas partidas Faceit CS2')
    .addStringOption((opt) =>
      opt.setName('nick').setDescription('Nickname Faceit')
    )
    .addIntegerOption((opt) =>
      opt
        .setName('qtd')
        .setDescription('Quantidade (1–5)')
        .setMinValue(1)
        .setMaxValue(5)
    ),

  async execute(interaction) {
    await interaction.deferReply();

    let nick = interaction.options.getString('nick') || getFaceitNick(interaction.user.id);
    const qtd = interaction.options.getInteger('qtd') || 5;

    if (!nick) {
      return interaction.editReply('Informe um nick ou use `/vincular` antes.');
    }

    try {
      const player = await getPlayerByNickname(nick);
      if (!player) return interaction.editReply(`Jogador \`${nick}\` não encontrado.`);

      const game = pickCsGame(player);
      const history = await getRecentMatches(player.player_id, game?.key || 'cs2', qtd);
      const items = history?.items || [];

      if (!items.length) {
        return interaction.editReply(`Nenhuma partida recente para \`${player.nickname}\`.`);
      }

      const lines = items.map((m, i) => {
        const score = m.results?.score
          ? `${m.results.score.faction1 ?? '?'}-${m.results.score.faction2 ?? '?'}`
          : '—';
        const mapName =
          m.i1 ||
          m.voting?.map?.pick?.[0] ||
          m.competition_name ||
          'partida';
        const date = m.finished_at ? `<t:${m.finished_at}:R>` : '';
        return `**${i + 1}.** \`${score}\` · ${mapName} ${date}`;
      });

      const embed = new EmbedBuilder()
        .setColor(0xf5a623)
        .setTitle(`Últimas partidas · ${player.nickname}`)
        .setDescription(lines.join('\n'))
        .setFooter({ text: 'MouseTrap Esports' })
        .setTimestamp();

      return interaction.editReply({ embeds: [embed] });
    } catch (err) {
      console.error(err);
      return interaction.editReply(`Erro: ${err.message}`);
    }
  },
};
