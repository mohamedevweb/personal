import React from 'react';
import {AbsoluteFill, useCurrentFrame} from 'remotion';
import {
  DotMatrix,
  GuideRails,
  Headline,
  NightGround,
  ParticleField,
  Reveal,
} from '../components';
import {IGNITIONS} from '../ignitions';
import {useLayout} from '../layout';
import {alpha, palette, type as typeScale} from '../theme';
import {fade} from '../motion';
import {beats, startOf} from '../timing';
import {families} from '../fonts';
import type {Copy} from '../copy';

/**
 * 120–270 · The claim.
 *
 * The cold open said what the product is. This says why it should exist — the
 * whole argument of the film in two lines. `creator` takes the signature; it is
 * the only red on screen. The turn is carried by the line break and the weight
 * of the sentence itself — Instrument Serif's italic is a true calligraphic
 * italic rather than a slant, and at 92px it read as decoration.
 *
 * The particle field keeps the cold open's clock so the ground does not restart
 * under the cut: the copy changes, the shot does not.
 */

export const Claim: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {px, shape, contentWidth} = useLayout();
  const continuous = frame + startOf('claim') - startOf('coldOpen');

  const size = shape === 'horizontal' ? 92 : 76;

  return (
    <AbsoluteFill>
      <NightGround />
      <DotMatrix opacity={0.08} />
      <GuideRails opacity={1} />
      <ParticleField opacity={fade({frame, start: 0, duration: 60, from: 1, to: 0.45})} frame={continuous} ignitions={IGNITIONS} />

      <AbsoluteFill
        style={{
          alignItems: 'center',
          justifyContent: 'center',
          textAlign: 'center',
          padding: px(80),
        }}
      >
        <div style={{maxWidth: contentWidth}}>
          <Reveal delay={beats.claim.lineOne} distance={10}>
            <Headline size={size} color={alpha.onNightPrimary}>
              {copy.claim.lineOneBefore}
              <span style={{color: palette.redLit}}>{copy.claim.lineOneLit}</span>
              {copy.claim.lineOneAfter}
            </Headline>
          </Reveal>

          <Reveal delay={beats.claim.lineTwo} distance={10}>
            <Headline size={size} color={alpha.onNightPrimary}>
              {copy.claim.lineTwo}
            </Headline>
          </Reveal>

          <Reveal delay={beats.claim.subtitle} distance={8}>
            <div
              style={{
                marginTop: px(38),
                marginLeft: 'auto',
                marginRight: 'auto',
                maxWidth: px(shape === 'horizontal' ? 720 : 640),
                fontFamily: families.body,
                fontWeight: 400,
                fontSize: px(shape === 'horizontal' ? 21 : 24),
                lineHeight: typeScale.bodyLeading,
                color: alpha.onNightSecondary,
              }}
            >
              {copy.claim.subtitle}
            </div>
          </Reveal>
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
