import React from 'react';
import {useCurrentFrame, useVideoConfig, interpolate} from 'remotion';
import {Body, Card, Eyebrow, ProductScene, Reveal} from '../components';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {alpha, palette, type as typeScale} from '../theme';
import {climbEase, enter, fade, seeded, travel} from '../motion';
import {beats} from '../timing';
import type {Copy} from '../copy';

/**
 * 540–780 · Step 2, Discover.
 *
 * One account, its last fourteen posts, indexed to its own average. Thirteen sit
 * near 1×. The fourteenth keeps climbing, and as it crosses it takes the red —
 * this is the third of red's three jobs: the single mark on a chart that is the
 * reason the chart exists.
 *
 * Then the chart collapses and the morning's three outliers stack up in its
 * place, because the chart was only ever the explanation of the list.
 */

const BAR_COUNT = 14;

/** Indexed to the account's own average, so 1 is the dashed line. */
const ORDINARY = new Array(BAR_COUNT - 1)
  .fill(true)
  .map((_, index) => 0.72 + seeded(index + 11) * 0.53);

const OUTLIER_PEAK = 8.4;

/**
 * A square-root scale. A linear one would put the outlier eight screens above
 * the card and flatten everything else into a rule; this keeps the ordinary
 * posts readable as a band and still lets the last bar leave them behind.
 */
const heightFor = (value: number, chartHeight: number) =>
  chartHeight * 0.3 * Math.sqrt(Math.max(value, 0));

export const Discover: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const {px, cardWidth, shape} = useLayout();

  const chartHeight = px(shape === 'horizontal' ? 250 : 300);
  const baselineY = heightFor(1, chartHeight);

  // The climb. It leaves the band at 2×, and that is where it takes the red.
  const climb = travel({
    frame,
    start: beats.discover.outlierClimb,
    duration: beats.discover.outlierClimbDuration,
    easing: climbEase,
  });
  const outlierValue = interpolate(climb, [0, 1], [1, OUTLIER_PEAK]);
  const isOutlier = outlierValue >= 2;
  const redness = interpolate(outlierValue, [1.8, 2.6], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  const collapse = travel({
    frame,
    start: beats.discover.collapse,
    duration: beats.discover.collapseDuration,
  });
  const badge = enter({frame, fps, delay: beats.discover.badge});

  return (
    <ProductScene
      step={copy.discover.step}
      title={copy.discover.title}
      body={copy.discover.body}
      headingDelay={beats.discover.heading}
    >
      <div style={{width: cardWidth}}>
        <Reveal delay={beats.discover.card} distance={14}>
          <Card padding={shape === 'horizontal' ? 34 : 40}>
            {/* The chart and the list occupy the same room; one leaves as the
                other arrives, so the card never changes size under the cut. */}
            <div style={{position: 'relative', minHeight: chartHeight + px(46)}}>
              <div
                style={{
                  opacity: 1 - collapse,
                  transform: `translateY(${-px(10) * collapse}px)`,
                }}
              >
                <Eyebrow size={shape === 'horizontal' ? 13 : 16}>
                  {copy.discover.chartCaption}
                </Eyebrow>

                <div
                  style={{
                    position: 'relative',
                    height: chartHeight,
                    marginTop: px(30),
                  }}
                >
                  <div
                    style={{
                      position: 'absolute',
                      inset: 0,
                      display: 'flex',
                      alignItems: 'flex-end',
                      gap: px(shape === 'horizontal' ? 16 : 18),
                    }}
                  >
                    {ORDINARY.map((value, index) => {
                      const grow = travel({
                        frame,
                        start: beats.discover.barsGrow + index * 1.5,
                        duration: beats.discover.barsGrowDuration,
                      });
                      return (
                        <div
                          key={index}
                          style={{
                            flex: '1 1 0',
                            height: heightFor(value, chartHeight) * grow,
                            backgroundColor: alpha.bar,
                            borderRadius: px(3),
                          }}
                        />
                      );
                    })}

                    <div
                      style={{
                        flex: '1 1 0',
                        position: 'relative',
                        height:
                          heightFor(outlierValue, chartHeight) *
                          travel({
                            frame,
                            start: beats.discover.barsGrow + (BAR_COUNT - 1) * 1.5,
                            duration: beats.discover.barsGrowDuration,
                          }),
                        backgroundColor: isOutlier ? palette.signature : alpha.bar,
                        opacity: isOutlier ? 0.35 + redness * 0.65 : 1,
                        borderRadius: px(3),
                      }}
                    />
                  </div>

                  {/* The account's own average. Everything is read against it. */}
                  <div
                    style={{
                      position: 'absolute',
                      left: 0,
                      right: 0,
                      bottom: baselineY,
                      height: Math.max(1, px(1)),
                      borderTop: `${Math.max(1, px(1))}px dashed ${alpha.baseline}`,
                      opacity: fade({frame, start: beats.discover.card + 6, duration: 12}),
                    }}
                  />
                  <div
                    style={{
                      position: 'absolute',
                      left: 0,
                      bottom: baselineY + px(8),
                      fontFamily: families.body,
                      fontWeight: 500,
                      fontSize: px(shape === 'horizontal' ? 12 : 15),
                      letterSpacing: typeScale.eyebrowTracking,
                      textTransform: 'uppercase',
                      color: palette.muted,
                      opacity: fade({frame, start: beats.discover.card + 8, duration: 12}),
                    }}
                  >
                    {copy.discover.baseline}
                  </div>

                  {/* The badge is the point of the whole shot, so it snaps
                      rather than fades, and it lands where the eye already is. */}
                  <div
                    style={{
                      position: 'absolute',
                      right: 0,
                      top: 0,
                      display: 'flex',
                      alignItems: 'center',
                      gap: px(12),
                      opacity: badge,
                      transform: `translateY(${px(8) * (1 - badge)}px)`,
                    }}
                  >
                    <div
                      style={{
                        fontFamily: families.body,
                        fontWeight: 600,
                        fontSize: px(shape === 'horizontal' ? 26 : 30),
                        color: palette.signature,
                        letterSpacing: '-0.02em',
                      }}
                    >
                      {copy.discover.badgeValue}
                    </div>
                    <Eyebrow size={shape === 'horizontal' ? 12 : 14}>
                      {copy.discover.badgeLabel}
                    </Eyebrow>
                  </div>
                </div>
              </div>

              <div
                style={{
                  position: 'absolute',
                  inset: 0,
                  opacity: collapse,
                  pointerEvents: 'none',
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'center',
                }}
              >
                {copy.discover.outliers.map((row, index) => {
                  const at = beats.discover.firstRow + index * beats.discover.rowStagger;
                  const rowIn = enter({frame, fps, delay: at});

                  return (
                    <div
                      key={row.handle}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: px(20),
                        paddingTop: px(shape === 'horizontal' ? 22 : 26),
                        paddingBottom: px(shape === 'horizontal' ? 22 : 26),
                        borderBottom:
                          index === copy.discover.outliers.length - 1
                            ? 'none'
                            : `${Math.max(1, px(1))}px solid ${palette.line}`,
                        opacity: rowIn,
                        transform: `translateY(${px(10) * (1 - rowIn)}px)`,
                      }}
                    >
                      <div
                        style={{
                          flex: '1 1 0',
                          fontFamily: families.body,
                          fontWeight: 500,
                          fontSize: px(shape === 'horizontal' ? 19 : 23),
                          color: palette.ink,
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {row.title}
                      </div>
                      <div
                        style={{
                          fontFamily: families.body,
                          fontWeight: 500,
                          fontSize: px(shape === 'horizontal' ? 16 : 19),
                          color: palette.muted,
                          minWidth: px(64),
                          textAlign: 'right',
                        }}
                      >
                        {row.views}
                      </div>
                      <div
                        style={{
                          fontFamily: families.body,
                          fontWeight: 600,
                          fontSize: px(shape === 'horizontal' ? 16 : 19),
                          color: palette.ink,
                          minWidth: px(56),
                          textAlign: 'right',
                        }}
                      >
                        {row.multiple}
                      </div>
                      <div
                        style={{
                          fontFamily: families.body,
                          fontWeight: 400,
                          fontSize: px(shape === 'horizontal' ? 15 : 18),
                          color: palette.muted,
                          minWidth: px(shape === 'horizontal' ? 150 : 170),
                          textAlign: 'right',
                        }}
                      >
                        {row.handle}
                      </div>
                      <div
                        style={{
                          fontFamily: families.body,
                          fontWeight: 400,
                          fontSize: px(shape === 'horizontal' ? 15 : 18),
                          color: palette.muted,
                          minWidth: px(36),
                          textAlign: 'right',
                        }}
                      >
                        {row.age}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </Card>
        </Reveal>

        <div
          style={{
            marginTop: px(22),
            opacity: fade({frame, start: beats.discover.footnote, duration: 14}),
          }}
        >
          <Body size={shape === 'horizontal' ? 16 : 19} color={palette.muted}>
            {copy.discover.footnote}
          </Body>
        </div>
      </div>
    </ProductScene>
  );
};
