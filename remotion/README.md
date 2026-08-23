# Personal — launch film

The 33-second launch film for **Personal**, built in Remotion. One argument, told
once: *you are a creator, here is what's working, here is what happened to you,
here is the post only you could have written.*

It is silent-first. Every claim is on screen as type, and the film must read with
the sound off — the score is an addition, never a crutch.

## Running it

```bash
npm install
```

```bash
npm run studio
```

Renders the horizontal cut:

```bash
npm run render
```

Renders all three shapes, H.264 at CRF 18, into `out/`:

```bash
./render.sh
```

A French cut is a data change, not a rewrite — all copy lives in `src/copy.ts`:

```bash
./render.sh fr
```

## Compositions

| id | size | use |
| --- | --- | --- |
| `LaunchFilm` | 1920×1080 | the primary cut |
| `LaunchFilmVertical` | 1080×1920 | reels, stories, shorts |
| `LaunchFilmSquare` | 1080×1080 | feed |

All three run the same scene components. Nothing is forked: the shape is read
from the `<Layout>` context in `src/layout.tsx`, which hands every scene a scale
factor (`px`) and whether its two halves stack or sit side by side.

Props (`language`, `hasScore`) are typed and editable in the Studio sidebar.

## The shot list

| frames | shot |
| --- | --- |
| 0–88 | Cold open, night. The field drifts, four points ignite, and the category lands: *Claude for personal brand.* |
| 88–180 | The claim. *You're a creator. Not a content machine.* |
| 180–222 | Turn to paper. A hard wipe with a signature hairline on its leading edge. |
| 222–360 | 01 Understand — Instagram connects, the read runs to forty, and the product writes down what it understood. |
| 360–550 | 02 Discover — one account against its own average, and the one post beating it. |
| 550–708 | 03 Connect — a working format, held against a story you already lived. |
| 708–876 | 04 Write — the draft, and the promise that it stays a draft. |
| 876–976 | End card, night. The mark, the wordmark, the line. |

Durations — not absolute marks — are the source of truth in `src/timing.ts`, so
shortening one shot moves every shot after it without any other number needing to
change. The beats inside each scene live there too. Retune the film there and
nowhere else.

## How it is put together

```
src/
  index.ts        registerRoot
  Root.tsx        the three compositions
  LaunchFilm.tsx  the running order, and the score's volume envelope
  timing.ts       every cut point and beat, in frames
  theme.ts        the palette — the only file allowed to write a colour
  copy.ts         every word, in every language
  layout.tsx      shape, scale and stacking
  motion.ts       the house spring and the three easings
  fonts.ts        Instrument Serif and Inter, loaded from public/fonts
  ignitions.ts    which points in the field light, and when
  components/     Card, Hairline, Eyebrow, Headline, StepLabel, Mark, Reveal, …
  scenes/         one file per shot
```

**Determinism.** Nothing measures the DOM, nothing animates from an effect, and
nothing calls `Math.random()` — the drift in the particle field comes from a
seeded hash in `motion.ts`. Any frame renders identically in isolation, which is
what makes distributed rendering and single-frame stills trustworthy.

**The italic is rationed too.** Instrument Serif's italic is a true calligraphic
italic, not a slant, and it is loud. It is used in exactly one place — the turn
in the claim, *Not a content machine.* Everywhere else the display face is set
upright.

**Red is rationed.** It belongs to three things and no others: the primary action
(the active format chip), a live indicator (the dot in the Understand card
header, the ignitions in the field), and the single mark on a chart that is the
reason the chart exists (the outlier bar, its badge, and the hairline that joins
the two cards in Connect). On night grounds it is `--red-lit`; on paper it is
`--signature`.

## Fonts

Both faces are served from `public/fonts` rather than fetched at render time, so
a render never waits on — or fails because of — the network, and every frame
rasterises identically.

`@remotion/google-fonts` stays the source of truth for which file is the current
release of each face; `scripts/vendor-fonts.mjs` reads the URLs out of it and
writes the files locally. Run it again after a Remotion upgrade:

```bash
node scripts/vendor-fonts.mjs
```

## Score

The film ships silent. To add a score, drop `score.mp3` into `public/` and render
with `hasScore` on:

```bash
npx remotion render LaunchFilm out/launch-film.mp4 --props='{"language":"en","hasScore":true}'
```

The volume envelope lives in `LaunchFilm.tsx`: it fades up over the first second,
holds, then ducks under the end card so the last line is read in something close
to silence, and is fully out across the final still.
