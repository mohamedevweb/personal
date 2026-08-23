/**
 * Pulls the two brand faces out of @remotion/google-fonts and writes them into
 * public/fonts, so that a render never touches the network. The package stays
 * the source of truth for which file is the current release of each face; this
 * script only copies what it points at. Run it again after upgrading Remotion.
 *
 *   node scripts/vendor-fonts.mjs
 */
import {mkdir, writeFile} from 'node:fs/promises';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';

import {getInfo as instrumentSerif} from '@remotion/google-fonts/InstrumentSerif';
import {getInfo as inter} from '@remotion/google-fonts/Inter';

const outDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'fonts');

/** Only latin is set anywhere in the film, so only latin is shipped. */
const wanted = [
  {info: instrumentSerif(), style: 'normal', weight: '400', file: 'instrument-serif-400.woff2'},
  {info: instrumentSerif(), style: 'italic', weight: '400', file: 'instrument-serif-400-italic.woff2'},
  {info: inter(), style: 'normal', weight: '400', file: 'inter-400.woff2'},
  {info: inter(), style: 'normal', weight: '500', file: 'inter-500.woff2'},
  {info: inter(), style: 'normal', weight: '600', file: 'inter-600.woff2'},
];

await mkdir(outDir, {recursive: true});

for (const {info, style, weight, file} of wanted) {
  const url = info.fonts[style]?.[weight]?.latin;
  if (!url) {
    throw new Error(`No latin ${style} ${weight} in ${info.fontFamily}`);
  }
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error(`${res.status} fetching ${url}`);
  }
  await writeFile(join(outDir, file), Buffer.from(await res.arrayBuffer()));
  console.log(`${info.fontFamily} ${style} ${weight} -> public/fonts/${file}`);
}
