import React from 'react';
import {AbsoluteFill, Freeze, useCurrentFrame, useVideoConfig} from 'remotion';
import {DotMatrix, GuideRails, Headline, Mark, NightGround, Reveal} from '../components';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {alpha, palette, type as typeScale} from '../theme';
import {travel, wipeEase} from '../motion';
import {beats, durationOf} from '../timing';
import type {Copy} from '../copy';
import {Write} from './Write';

/**
 * 1230–1350 · End card, night.
 *
 * The paper lifts away upward and the ground underneath it was always night. The
 * mark draws itself in, the wordmark sets beneath it, and the film says the one
 * thing it has been arguing for forty-five seconds.
 *
 * The last twenty frames do not move at all.
 */
export const EndCard: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {height} = useVideoConfig();
  const {px, shape} = useLayout();

  // Same curve as the wipe that brought us onto paper in the first place: the
  // film leaves the product the way it arrived at it.
  const lift = travel({
    frame,
    start: beats.endCard.lift,
    duration: beats.endCard.liftDuration,
    easing: wipeEase,
  });

  const markProgress = travel({
    frame,
    start: beats.endCard.mark,
    duration: beats.endCard.markDuration,
  });

  const markSize = px(shape === 'horizontal' ? 84 : 96);

  return (
    <AbsoluteFill>
      <NightGround />
      <DotMatrix opacity={0.06} />
      <GuideRails opacity={1} />

      {/* The draft, held on its last frame, leaving the way it came in. */}
      {lift < 1 ? (
        <AbsoluteFill style={{transform: `translateY(${-height * lift}px)`}}>
          <Freeze frame={durationOf('write') - 1}>
            <Write copy={copy} />
          </Freeze>
        </AbsoluteFill>
      ) : null}

      <AbsoluteFill
        style={{
          alignItems: 'center',
          justifyContent: 'center',
          textAlign: 'center',
          padding: px(80),
        }}
      >
        <Mark size={markSize} color={palette.redLit} progress={markProgress} />

        <Reveal delay={beats.endCard.wordmark} distance={8} style={{marginTop: px(30)}}>
          <Headline size={shape === 'horizontal' ? 62 : 68} color={alpha.onNightPrimary}>
            {copy.endCard.wordmark}
          </Headline>
        </Reveal>

        <Reveal delay={beats.endCard.line} distance={8} style={{marginTop: px(18)}}>
          <div
            style={{
              fontFamily: families.display,
              fontWeight: 400,
              fontSize: px(shape === 'horizontal' ? 30 : 34),
              letterSpacing: typeScale.displayTracking,
              color: alpha.onNightSecondary,
            }}
          >
            {copy.endCard.line}
          </div>
        </Reveal>

      </AbsoluteFill>

      <AbsoluteFill
        style={{
          alignItems: 'center',
          justifyContent: 'flex-end',
          paddingBottom: px(shape === 'horizontal' ? 64 : 96),
        }}
      >
        <Reveal delay={beats.endCard.url} distance={6}>
          <div
            style={{
              fontFamily: families.body,
              fontWeight: 400,
              fontSize: px(shape === 'horizontal' ? 16 : 19),
              letterSpacing: '0.02em',
              color: alpha.onNightFaint,
            }}
          >
            {copy.endCard.url}
          </div>
        </Reveal>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
