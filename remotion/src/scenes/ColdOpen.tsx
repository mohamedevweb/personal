import React from 'react';
import {AbsoluteFill, useCurrentFrame} from 'remotion';
import {DotMatrix, GuideRails, Headline, NightGround, ParticleField, Reveal} from '../components';
import {IGNITIONS} from '../ignitions';
import {useLayout} from '../layout';
import {alpha} from '../theme';
import {fade} from '../motion';
import {beats} from '../timing';
import type {Copy} from '../copy';

/**
 * 0–120 · Cold open, night.
 *
 * Essentially black. The dot matrix becomes perceptible, the field starts
 * drifting, and three or four points ignite in the brightened red — one at a
 * time, off the beat. Something is being detected.
 *
 * Then, after the last ignition has held, the category. One line, small, set in
 * the display face: what the product is, before anything is argued. It is not a
 * slogan and it is not dressed as one — no subtitle, no logo, no product. It
 * holds long enough to read and leaves cleanly, so it never shares the screen
 * with the claim that follows.
 */
export const ColdOpen: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {px, shape, contentWidth} = useLayout();

  const matrix = fade({frame, start: beats.coldOpen.matrixFadeIn, duration: 46, to: 0.08});
  const field = fade({frame, start: 4, duration: 40});

  // The line leaves on a plain fade. Anything else would be a gesture, and the
  // sentence is meant to feel obvious rather than performed.
  const categoryOut = fade({
    frame,
    start: beats.coldOpen.categoryOut,
    duration: beats.coldOpen.categoryOutDuration,
    from: 1,
    to: 0,
  });

  return (
    <AbsoluteFill>
      <NightGround />
      <DotMatrix opacity={matrix} />
      <GuideRails opacity={fade({frame, start: 24, duration: 40})} />
      <ParticleField opacity={field} ignitions={IGNITIONS} />

      <AbsoluteFill
        style={{
          alignItems: 'center',
          justifyContent: 'center',
          textAlign: 'center',
          padding: px(80),
          opacity: categoryOut,
        }}
      >
        <Reveal delay={beats.coldOpen.category} distance={6} style={{maxWidth: contentWidth}}>
          <Headline
            size={shape === 'horizontal' ? 38 : 34}
            color={alpha.onNightPrimary}
          >
            {copy.coldOpen.category}
          </Headline>
        </Reveal>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
