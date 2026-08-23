import React from 'react';
import {AbsoluteFill, useCurrentFrame, useVideoConfig} from 'remotion';
import {Body, Card, Eyebrow, PaperGround, Reveal, StepLabel} from '../components';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {palette, type as typeScale} from '../theme';
import {enter, travel} from '../motion';
import {beats} from '../timing';
import type {Copy} from '../copy';

/**
 * 780–1020 · Step 3, Connect.
 *
 * The emotional centre. A format that is working in the niche, and a thing that
 * actually happened to you this week, brought together until they touch. Then a
 * single red hairline draws between them and both cards settle half a pixel
 * closer — the whole product, in one gesture.
 *
 * This scene leaves the two-column frame the other steps share, because the
 * cards have to meet in the middle of the picture rather than in the middle of a
 * column. Nothing new arrives after the join: the last two seconds are air.
 */
export const Connect: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const {px, gutter, stacked, contentWidth, shape} = useLayout();

  const approach = travel({
    frame,
    start: beats.connect.cardsEnter,
    duration: beats.connect.cardsTravel,
  });

  // How far out each card starts, measured along the axis it travels on.
  const throwDistance = stacked ? px(320) : contentWidth * 0.42;
  const offset = throwDistance * (1 - approach);

  const join = travel({
    frame,
    start: beats.connect.join,
    duration: beats.connect.joinDuration,
  });

  // Half a pixel. It should be felt rather than seen.
  const settle = travel({frame, start: beats.connect.settle, duration: 20}) * px(0.5);

  const cardWidth = stacked ? contentWidth : contentWidth * 0.42;
  const gap = px(stacked ? 54 : 92);

  const pair = [
    {
      label: copy.connect.patternLabel,
      quote: copy.connect.patternQuote,
      direction: -1,
    },
    {
      label: copy.connect.momentLabel,
      quote: copy.connect.momentQuote,
      direction: 1,
    },
  ];

  return (
    <AbsoluteFill>
      <PaperGround />

      <AbsoluteFill style={{padding: gutter, display: 'flex', flexDirection: 'column'}}>
        <div>
          <Reveal delay={beats.connect.heading} distance={10}>
            <StepLabel
              step={copy.connect.step}
              title={copy.connect.title}
              size={shape === 'horizontal' ? 54 : 62}
            />
          </Reveal>
          <Reveal delay={beats.connect.heading + 2} distance={10}>
            <Body
              size={shape === 'horizontal' ? 22 : 26}
              color={palette.muted}
              style={{marginTop: px(18), maxWidth: px(620)}}
            >
              {copy.connect.body}
            </Body>
          </Reveal>
        </div>

        <div
          style={{
            flex: '1 1 0',
            display: 'flex',
            flexDirection: stacked ? 'column' : 'row',
            alignItems: 'center',
            justifyContent: 'center',
            position: 'relative',
          }}
        >
          {pair.map(({label, quote, direction}, index) => {
            const arrival = enter({frame, fps, delay: beats.connect.cardsEnter + index * 2});
            const travelOffset = offset * direction;
            const settleOffset = -settle * direction;

            return (
              <div
                key={label}
                style={{
                  width: cardWidth,
                  opacity: arrival,
                  transform: stacked
                    ? `translateY(${travelOffset + settleOffset}px)`
                    : `translateX(${travelOffset + settleOffset}px)`,
                  marginTop: stacked && index === 1 ? gap : 0,
                  marginLeft: !stacked && index === 1 ? gap : 0,
                }}
              >
                <Card
                  padding={shape === 'horizontal' ? 40 : 44}
                  lifted
                  // A fixed height on both, so a one-line quote and a two-line
                  // quote still read as a matched pair rather than two things.
                  style={{
                    minHeight: px(shape === 'horizontal' ? 230 : 260),
                    display: 'flex',
                    flexDirection: 'column',
                  }}
                >
                  <Eyebrow size={shape === 'horizontal' ? 13 : 16}>{label}</Eyebrow>
                  <div
                    style={{
                      flex: '1 1 0',
                      display: 'flex',
                      alignItems: 'center',
                      marginTop: px(24),
                      fontFamily: families.display,
                      fontStyle: 'italic',
                      fontWeight: 400,
                      fontSize: px(shape === 'horizontal' ? 34 : 38),
                      lineHeight: 1.16,
                      letterSpacing: typeScale.displayTracking,
                      color: palette.ink,
                    }}
                  >
                    {quote}
                  </div>
                </Card>
              </div>
            );
          })}

          {/* The join. One hairline, drawn from the middle out, in the only red
              this scene is allowed. */}
          <div
            style={{
              position: 'absolute',
              width: stacked ? Math.max(2, px(2)) : gap,
              height: stacked ? gap : Math.max(2, px(2)),
              backgroundColor: palette.signature,
              transform: stacked ? `scaleY(${join})` : `scaleX(${join})`,
              opacity: join,
            }}
          />
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
