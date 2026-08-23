import {continueRender, delayRender, staticFile} from 'remotion';
import {fontFamily as instrumentSerif} from '@remotion/google-fonts/InstrumentSerif';
import {fontFamily as inter} from '@remotion/google-fonts/Inter';

/**
 * The two brand faces, served from public/fonts rather than from Google, so a
 * render never waits on the network and every frame rasterises identically in
 * isolation. The family names still come from @remotion/google-fonts, and
 * `scripts/vendor-fonts.mjs` pulls the files it points at — see the README.
 */

export const families = {
  display: instrumentSerif,
  body: inter,
} as const;

const faces = [
  {family: instrumentSerif, file: 'instrument-serif-400.woff2', weight: 400, style: 'normal'},
  {family: instrumentSerif, file: 'instrument-serif-400-italic.woff2', weight: 400, style: 'italic'},
  {family: inter, file: 'inter-400.woff2', weight: 400, style: 'normal'},
  {family: inter, file: 'inter-500.woff2', weight: 500, style: 'normal'},
  {family: inter, file: 'inter-600.woff2', weight: 600, style: 'normal'},
] as const;

let loaded: Promise<void> | null = null;

/**
 * Idempotent: called once at module scope by the root, and safe to call again.
 * Remotion is held on `delayRender` until every face is ready, so no frame can
 * be captured mid-swap.
 */
export const loadBrandFonts = (): Promise<void> => {
  if (loaded) {
    return loaded;
  }

  if (typeof document === 'undefined' || typeof FontFace === 'undefined') {
    loaded = Promise.resolve();
    return loaded;
  }

  const handle = delayRender('Loading Instrument Serif and Inter');

  loaded = Promise.all(
    faces.map(async ({family, file, weight, style}) => {
      const face = new FontFace(family, `url(${staticFile(`fonts/${file}`)}) format('woff2')`, {
        weight: String(weight),
        style,
      });
      await face.load();
      // FontFaceSet is typed as a plain Set in some lib.dom releases, which
      // hides `add`. The call itself is standard and supported everywhere
      // Remotion renders.
      (document.fonts as unknown as {add: (f: FontFace) => void}).add(face);
    }),
  )
    .then(() => {
      continueRender(handle);
    })
    .catch((err: unknown) => {
      continueRender(handle);
      throw err;
    });

  return loaded;
};
