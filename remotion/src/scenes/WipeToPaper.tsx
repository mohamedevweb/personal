import React from 'react';
import {AbsoluteFill, Freeze, useCurrentFrame, useVideoConfig} from 'remotion';
import {palette} from '../theme';
import {travel, wipeEase} from '../motion';
import {beats, durationOf} from '../timing';
import {useLayout} from '../layout';
import type {Copy} from '../copy';
import {Claim} from './Claim';

/**
 * 270–330 · Turn to paper.
 *
 * Not a crossfade. A hard horizontal wipe with a 2px signature hairline riding
 * its leading edge — the one moment the red is allowed to be a moving object,
 * and it is gone in under two seconds.
 *
 * The claim is held frozen underneath so the cut does not blink: the sentence is
 * still there right up until the paper covers it.
 */
export const WipeToPaper: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {width} = useVideoConfig();
  const {px} = useLayout();

  const progress = travel({
    frame,
    start: beats.wipe.start,
    duration: beats.wipe.duration,
    easing: wipeEase,
  });

  const edge = progress * width;
  const rule = Math.max(2, px(2));

  return (
    <AbsoluteFill>
      <Freeze frame={durationOf('claim') - 1}>
        <Claim copy={copy} />
      </Freeze>

      <AbsoluteFill
        style={{
          backgroundColor: palette.ivory,
          width: edge,
          right: 'auto',
        }}
      />

      {progress > 0 && progress < 1 ? (
        <div
          style={{
            position: 'absolute',
            left: edge - rule,
            top: 0,
            bottom: 0,
            width: rule,
            backgroundColor: palette.signature,
          }}
        />
      ) : null}
    </AbsoluteFill>
  );
};
