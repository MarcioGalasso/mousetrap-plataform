'use strict';

const { SlashCommandBuilder } = require('discord.js');
const { getPlayerByNickname } = require('../utils/faceit');
const { setFaceitNick, unlinkFaceit, getFaceitNick } = require('../utils/links');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('vincular')
    .setDescription('Vincula seu Discord a um nick Faceit')
    .addSubcommand((sub) =>
      sub
        .setName('faceit')
        .setDescription('Salva seu nick Faceit')
        .addStringOption((opt) =>
          opt.setName('nick').setDescription('Seu nickname na Faceit').setRequired(true)
        )
    )
    .addSubcommand((sub) =>
      sub.setName('remover').setDescription('Remove o vínculo Faceit')
    )
    .addSubcommand((sub) =>
      sub.setName('ver').setDescription('Mostra o nick vinculado')
    ),

  async execute(interaction) {
    const sub = interaction.options.getSubcommand();

    if (sub === 'remover') {
      unlinkFaceit(interaction.user.id);
      return interaction.reply({ content: 'Vínculo Faceit removido.', ephemeral: true });
    }

    if (sub === 'ver') {
      const nick = getFaceitNick(interaction.user.id);
      return interaction.reply({
        content: nick ? `Seu nick Faceit: **${nick}**` : 'Nenhum nick vinculado. Use `/vincular faceit`.',
        ephemeral: true,
      });
    }

    const nick = interaction.options.getString('nick', true);
    await interaction.deferReply({ ephemeral: true });

    try {
      const player = await getPlayerByNickname(nick);
      if (!player) {
        return interaction.editReply(`Nick \`${nick}\` não encontrado na Faceit.`);
      }
      setFaceitNick(interaction.user.id, player.nickname);
      return interaction.editReply(
        `Vinculado a **${player.nickname}**. Agora /faceit e /last funcionam sem informar o nick.`
      );
    } catch (err) {
      return interaction.editReply(`Erro: ${err.message}`);
    }
  },
};
