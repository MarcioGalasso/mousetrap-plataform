'use strict';

const fs = require('fs');
const path = require('path');

const LINKS_PATH = path.join(__dirname, '../../data/links.json');

function ensureLinksFile() {
  const dir = path.dirname(LINKS_PATH);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  if (!fs.existsSync(LINKS_PATH)) fs.writeFileSync(LINKS_PATH, '{}', 'utf8');
}

function loadLinks() {
  ensureLinksFile();
  try {
    return JSON.parse(fs.readFileSync(LINKS_PATH, 'utf8'));
  } catch {
    return {};
  }
}

function saveLinks(data) {
  ensureLinksFile();
  fs.writeFileSync(LINKS_PATH, JSON.stringify(data, null, 2), 'utf8');
}

function getFaceitNick(discordUserId) {
  const links = loadLinks();
  return links[discordUserId] || null;
}

function setFaceitNick(discordUserId, nick) {
  const links = loadLinks();
  links[discordUserId] = nick;
  saveLinks(links);
}

function unlinkFaceit(discordUserId) {
  const links = loadLinks();
  delete links[discordUserId];
  saveLinks(links);
}

module.exports = { getFaceitNick, setFaceitNick, unlinkFaceit };
