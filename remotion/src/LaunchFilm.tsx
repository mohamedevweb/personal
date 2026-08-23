import React from 'react';
import {AbsoluteFill, Audio, Series, interpolate, staticFile, useCurrentFrame} from 'remotion';
import {z} from 'zod';

import {Layout} from './layout';
import {loadBrandFonts} from './fonts';
import {palette} from './theme';
import {beats, durationOf, startOf} from './timing';
import {copyFor, cuts} from './copy';
import {ColdOpen} from './scenes/ColdOpen';
import {Claim} from './scenes/Claim';
import {WipeToPaper} from './scenes/WipeToPaper';
import {Understand} from './scenes/Understand';
import {Discover} from './scenes/Discover';
import {Connect} from './scenes/Connect';
import {Write} from './scenes/Write';
import {EndCard} from './scenes/EndCard';

void loadBrandFonts();

export const launchFilmSchema = z.object({
  /** Which cut of the copy to set. The film is identical in every other way. */
  language: z.enum(Object.keys(cuts) as ['en', 'fr']),
  /**
   * The film is silent-first: every claim is on screen as type, and it must
   * read with the sound off. The score is an addition, never a crutch.
   */
  hasScore: z.boolean(),
});

export type LaunchFilmProps = z.infer<typeof launchFilmSchema>;

export const defaultLaunchFilmProps: LaunchFilmProps = {
  language: 'en',
  hasScore: false,
};

/**
 * The score ducks under the end card so the last line is read in something close
 * to silence, and fades out entirely across the final still.
 */
const Score: React.FC = () => {
  const frame = useCurrentFrame();

  const volume = interpolate(
    frame,
    [
      0,
      30,
      startOf('endCard'),
      startOf('endCard') + beats.endCard.wordmark,
      startOf('endCard') + beats.endCard.stillFrom,
    ],
    [0, 0.7, 0.7, 0.28, 0],
    {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'},
  );

  return <Audio src={staticFile('score.mp3')} volume={volume} />;
};

/**
 * The film. Eight shots, one argument: you are a creator, here is what is
 * working, here is what happened to you, here is the post only you could have
 * written.
 *
 * Every cut point comes from `timing.ts`; this file only says what order the
 * shots go in. The same components render all three aspect ratios — the shape
 * is read from `<Layout>`, never forked here.
 */
export const LaunchFilm: React.FC<LaunchFilmProps> = ({language, hasScore}) => {
  const copy = copyFor(language);

  return (
    <Layout>
      <AbsoluteFill style={{backgroundColor: palette.night}}>
        <Series>
          <Series.Sequence durationInFrames={durationOf('coldOpen')} name="Cold open">
            <ColdOpen copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('claim')} name="The claim">
            <Claim copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('wipe')} name="Turn to paper">
            <WipeToPaper copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('understand')} name="01 Understand">
            <Understand copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('discover')} name="02 Discover">
            <Discover copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('connect')} name="03 Connect">
            <Connect copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('write')} name="04 Write">
            <Write copy={copy} />
          </Series.Sequence>

          <Series.Sequence durationInFrames={durationOf('endCard')} name="End card">
            <EndCard copy={copy} />
          </Series.Sequence>
        </Series>

        {hasScore ? <Score /> : null}
      </AbsoluteFill>
    </Layout>
  );
};
