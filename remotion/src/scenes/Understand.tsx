import React from 'react';
import {interpolate, useCurrentFrame, useVideoConfig} from 'remotion';
import {
  Card,
  Eyebrow,
  Hairline,
  InstagramGlyph,
  ProductScene,
  Reveal,
  TextReveal,
} from '../components';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {alpha, palette, radius} from '../theme';
import {enter, fade, travel} from '../motion';
import {beats} from '../timing';
import type {Copy} from '../copy';

/**
 * 330–490 · Step 1, Understand.
 *
 * The connection happens on screen, in order, because "connect once" is the
 * whole promise of the step: the account arrives, it reads as connected, the
 * read runs to forty — and only then does the product start writing down what
 * it understood.
 *
 * The values arrive on a fast character reveal because the fiction is that they
 * are being written, not that they are being animated. One red in the shot: the
 * live dot against the count.
 */
export const Understand: React.FC<{copy: Copy}> = ({copy}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const {px, cardWidth, shape} = useLayout();

  const wide = shape === 'horizontal';

  const account = enter({frame, fps, delay: beats.understand.account});
  const connected = enter({frame, fps, delay: beats.understand.connected});

  // The read itself. The count and the rail are the same number, shown twice.
  const read = travel({
    frame,
    start: beats.understand.readStart,
    duration: beats.understand.readDuration,
  });
  const postsRead = Math.round(interpolate(read, [0, 1], [0, 40]));
  const status =
    read < 1
      ? copy.understand.reading.replace('{n}', String(postsRead))
      : copy.understand.cardLabel;

  return (
    <ProductScene
      step={copy.understand.step}
      title={copy.understand.title}
      body={copy.understand.body}
      headingDelay={beats.understand.heading}
    >
      <Reveal delay={beats.understand.card} distance={14}>
        <Card width={cardWidth} padding={wide ? 36 : 42}>
          <div style={{display: 'flex', alignItems: 'center', gap: px(14)}}>
            <div
              style={{
                opacity: account,
                transform: `translateY(${px(6) * (1 - account)}px)`,
                display: 'flex',
                alignItems: 'center',
                gap: px(14),
              }}
            >
              <InstagramGlyph size={px(wide ? 26 : 31)} color={palette.ink} />
              <span
                style={{
                  fontFamily: families.body,
                  fontWeight: 500,
                  fontSize: px(wide ? 20 : 24),
                  color: palette.ink,
                }}
              >
                {copy.understand.handle}
              </span>
            </div>

            {/* Stamped once the handshake is done, and then it just sits there. */}
            <div
              style={{
                marginLeft: 'auto',
                opacity: connected,
                transform: `translateY(${px(6) * (1 - connected)}px)`,
                padding: `${px(6)}px ${px(14)}px`,
                borderRadius: px(radius.chip),
                backgroundColor: alpha.chipIdle,
                fontFamily: families.body,
                fontWeight: 500,
                fontSize: px(wide ? 13 : 16),
                color: palette.muted,
              }}
            >
              {copy.understand.connected}
            </div>
          </div>

          {/* The read, as a rail. It fills once and never resets. */}
          <div
            style={{
              marginTop: px(24),
              height: Math.max(2, px(2)),
              borderRadius: px(2),
              backgroundColor: alpha.chipIdle,
              opacity: fade({frame, start: beats.understand.readStart - 4, duration: 8}),
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                width: '100%',
                height: '100%',
                backgroundColor: palette.muted,
                transform: `scaleX(${read})`,
                transformOrigin: 'left center',
              }}
            />
          </div>

          <div
            style={{
              marginTop: px(14),
              display: 'flex',
              alignItems: 'center',
              gap: px(10),
              opacity: fade({frame, start: beats.understand.readStart - 2, duration: 8}),
            }}
          >
            <div
              style={{
                width: px(8),
                height: px(8),
                borderRadius: '50%',
                backgroundColor: palette.signature,
                // Alive, never blinking.
                opacity: 0.55 + 0.45 * Math.sin(frame / 7),
              }}
            />
            <Eyebrow size={wide ? 13 : 16}>{status}</Eyebrow>
          </div>

          <Hairline style={{marginTop: px(26), marginBottom: px(4)}} />

          {copy.understand.rows.map((row, index) => {
            const at = beats.understand.firstRow + index * beats.understand.rowStagger;
            const rowIn = fade({frame, start: at, duration: 8});
            const value = travel({
              frame,
              start: at + 3,
              duration: beats.understand.rowReveal,
            });

            return (
              <div
                key={row.label}
                style={{
                  display: 'flex',
                  alignItems: 'baseline',
                  gap: px(20),
                  paddingTop: px(wide ? 18 : 22),
                  paddingBottom: px(wide ? 18 : 22),
                  borderBottom:
                    index === copy.understand.rows.length - 1
                      ? 'none'
                      : `${Math.max(1, px(1))}px solid ${palette.line}`,
                  opacity: rowIn,
                }}
              >
                <div
                  style={{
                    flex: `0 0 ${px(wide ? 120 : 150)}px`,
                    fontFamily: families.body,
                    fontWeight: 500,
                    fontSize: px(wide ? 15 : 18),
                    color: palette.muted,
                  }}
                >
                  {row.label}
                </div>
                <div
                  style={{
                    fontFamily: families.body,
                    fontWeight: 500,
                    fontSize: px(wide ? 21 : 25),
                    color: palette.ink,
                  }}
                >
                  <TextReveal text={row.value} progress={value} />
                </div>
              </div>
            );
          })}
        </Card>
      </Reveal>
    </ProductScene>
  );
};
