import React from 'react';
import {useCurrentFrame, useVideoConfig} from 'remotion';
import {Card, Eyebrow, Hairline, ProductScene, Reveal, TextReveal} from '../components';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {palette, radius, alpha, type as typeScale} from '../theme';
import {enter, travel} from '../motion';
import {beats} from '../timing';
import type {Copy} from '../copy';

/**
 * 1020–1230 · Step 4, Write.
 *
 * The two cards resolve into one draft: a hook in the display face, the three
 * beats of the structure under it, and the caption. It writes itself, quickly,
 * because that is what is actually happening. The active format chip is the only
 * red — it is the primary action.
 *
 * And then the stamp, which is the promise the whole product rests on: it never
 * posts for you.
 */
export const Write: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const {px, cardWidth, shape} = useLayout();

  const hook = travel({frame, start: beats.write.hook, duration: 16});
  const caption = travel({frame, start: beats.write.caption, duration: 20});
  const stamp = enter({frame, fps, delay: beats.write.stamp});

  return (
    <ProductScene
      step={copy.write.step}
      title={copy.write.title}
      body={copy.write.body}
      headingDelay={beats.write.heading}
    >
      <div style={{width: cardWidth, position: 'relative'}}>
        <Reveal delay={beats.write.resolve} distance={14}>
          <Card padding={shape === 'horizontal' ? 36 : 42} lifted>
            <div style={{display: 'flex', gap: px(10)}}>
              {copy.write.formats.map((format, index) => {
                const active = format === copy.write.activeFormat;
                const chip = enter({frame, fps, delay: beats.write.chips + index * 2});

                return (
                  <div
                    key={format}
                    style={{
                      opacity: chip,
                      transform: `translateY(${px(6) * (1 - chip)}px)`,
                      padding: `${px(8)}px ${px(18)}px`,
                      borderRadius: px(radius.chip),
                      backgroundColor: active ? palette.signature : alpha.chipIdle,
                      color: active ? palette.surface : palette.muted,
                      fontFamily: families.body,
                      fontWeight: 500,
                      fontSize: px(shape === 'horizontal' ? 15 : 18),
                    }}
                  >
                    {format}
                  </div>
                );
              })}
            </div>

            <Hairline style={{marginTop: px(30), marginBottom: px(30)}} />

            <div
              style={{
                fontFamily: families.display,
                fontWeight: 400,
                fontSize: px(shape === 'horizontal' ? 40 : 46),
                lineHeight: 1.12,
                letterSpacing: typeScale.displayTracking,
                color: palette.ink,
              }}
            >
              <TextReveal text={copy.write.hook} progress={hook} />
            </div>

            <div style={{marginTop: px(30)}}>
              {copy.write.beats.map((beat, index) => {
                const at = beats.write.beatsStart + index * beats.write.beatStagger;
                const beatIn = travel({frame, start: at, duration: 12});

                return (
                  <div
                    key={beat}
                    style={{
                      display: 'flex',
                      alignItems: 'baseline',
                      gap: px(16),
                      marginBottom: px(14),
                      opacity: beatIn > 0 ? 1 : 0,
                    }}
                  >
                    <span
                      style={{
                        fontFamily: families.body,
                        fontWeight: 500,
                        fontSize: px(shape === 'horizontal' ? 13 : 16),
                        color: palette.muted,
                        letterSpacing: typeScale.eyebrowTracking,
                      }}
                    >
                      {String(index + 1).padStart(2, '0')}
                    </span>
                    <span
                      style={{
                        fontFamily: families.body,
                        fontWeight: 400,
                        fontSize: px(shape === 'horizontal' ? 19 : 23),
                        color: palette.ink,
                      }}
                    >
                      <TextReveal text={beat} progress={beatIn} />
                    </span>
                  </div>
                );
              })}
            </div>

            <div
              style={{
                marginTop: px(24),
                paddingTop: px(24),
                borderTop: `${Math.max(1, px(1))}px solid ${palette.line}`,
              }}
            >
              <Eyebrow size={shape === 'horizontal' ? 12 : 15}>
                {copy.write.captionLabel}
              </Eyebrow>
              <div
                style={{
                  marginTop: px(14),
                  fontFamily: families.body,
                  fontWeight: 400,
                  fontSize: px(shape === 'horizontal' ? 18 : 22),
                  lineHeight: 1.55,
                  color: palette.muted,
                }}
              >
                <TextReveal text={copy.write.caption} progress={caption} />
              </div>
            </div>
          </Card>
        </Reveal>

        {/* Bottom-right, half off the card, like something pressed onto it. */}
        <div
          style={{
            position: 'absolute',
            right: px(-10),
            bottom: px(-22),
            opacity: stamp,
            transform: `translateY(${px(10) * (1 - stamp)}px)`,
            backgroundColor: palette.ink,
            color: palette.ivory,
            borderRadius: px(radius.badge),
            padding: `${px(10)}px ${px(18)}px`,
            fontFamily: families.body,
            fontWeight: 500,
            fontSize: px(shape === 'horizontal' ? 15 : 18),
          }}
        >
          {copy.write.stamp}
        </div>
      </div>
    </ProductScene>
  );
};
