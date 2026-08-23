import React from 'react';
import {
  AbsoluteFill,
  Audio,
  Sequence,
  Series,
  interpolate,
  staticFile,
  useCurrentFrame,
} from 'remotion';
import {z} from 'zod';

import {Layout} from './layout';
import {loadBrandFonts} from './fonts';
import {palette} from './theme';
import {beats, durationOf, startOf} from './timing';
import {copyFor, cuts, type CutLanguage} from './copy';
import {VOICE_FORMAT, voiceoverFor} from './voiceover';
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
   * read with the sound off. Both of these are additions, never crutches.
   */
  hasScore: z.boolean(),
  hasVoice: z.boolean(),
});

export type LaunchFilmProps = z.infer<typeof launchFilmSchema>;

export const defaultLaunchFilmProps: LaunchFilmProps = {
  language: 'en',
  hasScore: false,
  hasVoice: true,
};

/**
 * The score ducks under the end card so the last line is read in something close
 * to silence, and fades out entirely across the final still. It sits lower
 * still when there is a voice over it — the voice is the foreground.
 */
const Score: React.FC<{under: number}> = ({under}) => {
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

  return <Audio src={staticFile('score.mp3')} volume={volume * under} />;
};

/**
 * The read, one line per shot. Each line is anchored to its own shot's first
 * frame, so retiming a shot carries its line along instead of sliding the whole
 * track out of sync.
 */
const Voiceover: React.FC<{language: CutLanguage}> = ({language}) => (
  <>
    {voiceoverFor(language).map((line) => (
      <Sequence
        key={line.file}
        from={startOf(line.scene) + line.offset}
        name={`Voice · ${line.file}`}
      >
        <Audio src={staticFile(`voice/${language}/${line.file}.${VOICE_FORMAT}`)} />
      </Sequence>
    ))}
  </>
);

/**
 * The film. Eight shots, one argument: you are a creator, here is what is
 * working, here is what happened to you, here is the post only you could have
 * written.
 *
 * Every cut point comes from `timing.ts`; this file only says what order the
 * shots go in. The same components render all three aspect ratios — the shape
 * is read from `<Layout>`, never forked here.
 */
export const LaunchFilm: React.FC<LaunchFilmProps> = ({language, hasScore, hasVoice}) => {
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

        {hasScore ? <Score under={hasVoice ? 0.45 : 1} /> : null}
        {hasVoice ? <Voiceover language={language} /> : null}
      </AbsoluteFill>
    </Layout>
  );
};
