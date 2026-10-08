'use strict';

const FACEIT_BASE = 'https://open.faceit.com/data/v4';

function apiKey() {
  const key = process.env.FACEIT_API_KEY;
  if (!key) throw new Error('FACEIT_API_KEY não configurada no .env');
  return key;
}

async function faceitFetch(pathname) {
  const res = await fetch(`${FACEIT_BASE}${pathname}`, {
    headers: {
      Authorization: `Bearer ${apiKey()}`,
      Accept: 'application/json',
    },
  });

  if (res.status === 404) return null;
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Faceit API ${res.status}: ${text.slice(0, 180)}`);
  }
  return res.json();
}

/** Busca jogador CS2 (game = cs2). Fallback para csgo se não achar. */
async function getPlayerByNickname(nickname) {
  let data = await faceitFetch(`/players?nickname=${encodeURIComponent(nickname)}&game=cs2`);
  if (!data) {
    data = await faceitFetch(`/players?nickname=${encodeURIComponent(nickname)}&game=csgo`);
  }
  return data;
}

async function getPlayerStats(playerId, game = 'cs2') {
  let data = await faceitFetch(`/players/${playerId}/stats/${game}`);
  if (!data && game === 'cs2') {
    data = await faceitFetch(`/players/${playerId}/stats/csgo`);
  }
  return data;
}

async function getRecentMatches(playerId, game = 'cs2', limit = 5) {
  let data = await faceitFetch(
    `/players/${playerId}/history?game=${game}&offset=0&limit=${limit}`
  );
  if ((!data || !data.items?.length) && game === 'cs2') {
    data = await faceitFetch(
      `/players/${playerId}/history?game=csgo&offset=0&limit=${limit}`
    );
  }
  return data;
}

function pickCsGame(player) {
  if (player?.games?.cs2) return { key: 'cs2', data: player.games.cs2 };
  if (player?.games?.csgo) return { key: 'csgo', data: player.games.csgo };
  return null;
}

module.exports = {
  getPlayerByNickname,
  getPlayerStats,
  getRecentMatches,
  pickCsGame,
};
