import React from 'react';
import {AbsoluteFill, useCurrentFrame, useVideoConfig} from 'remotion';
import {alpha, palette} from '../theme';
import {seeded} from '../motion';
import {useLayout} from '../layout';

/**
 * The two grounds and the three textures that live on night. Textures are
 * ground, not content: they sit at single-digit opacity and never compete with
 * type. Everything here is derived from the frame number and a fixed seed, so a
 * frame rendered in isolation is identical to the same frame in a full pass.
 */

export const DotMatrix: React.FC<{opacity: number; spacing?: number}> = ({
  opacity,
  spacing = 34,
}) => {
  const {px} = useLayout();
  const step = px(spacing);
  const dot = Math.max(1, px(1.6));

  return (
    <AbsoluteFill
      style={{
        opacity,
        backgroundImage: `radial-gradient(${alpha.matrixDot} ${dot}px, transparent ${dot}px)`,
        backgroundSize: `${step}px ${step}px`,
      }}
    />
  );
};

export const GuideRails: React.FC<{opacity: number}> = ({opacity}) => {
  const {px} = useLayout();
  const thickness = Math.max(1, px(1));

  return (
    <AbsoluteFill style={{opacity}}>
      {[25, 50, 75].map((position) => (
        <div
          key={position}
          style={{
            position: 'absolute',
            left: `${position}%`,
            top: 0,
            bottom: 0,
            width: thickness,
            backgroundColor: alpha.guideRail,
          }}
        />
      ))}
    </AbsoluteFill>
  );
};

export type Ignition = {
  /** Index into the particle field. Fixed, so the same points always light. */
  particle: number;
  /** Frame at which it ignites, relative to the field's own clock. */
  frame: number;
};

const PARTICLE_COUNT = 64;

/**
 * A slow drift, seeded once. A few points are lit — this is one of red's three
 * jobs in the film, and the count is deliberately tiny. On a near-black ground
 * the red is the brightened one; the signature itself goes muddy down here.
 */
export const ParticleField: React.FC<{
  opacity: number;
  ignitions?: readonly Ignition[];
  frame?: number;
  litColor?: string;
}> = ({opacity, ignitions = [], frame, litColor = palette.redLit}) => {
  const currentFrame = useCurrentFrame();
  const {width, height, fps} = useVideoConfig();
  const {px} = useLayout();
  const clock = frame ?? currentFrame;
  const seconds = clock / fps;

  return (
    <AbsoluteFill style={{opacity}}>
      <svg width={width} height={height} style={{position: 'absolute', inset: 0}}>
        {new Array(PARTICLE_COUNT).fill(true).map((_, index) => {
          const baseX = seeded(index + 1) * width;
          const baseY = seeded(index + 101) * height;
          const driftX = Math.sin(seconds * 0.16 + seeded(index + 201) * 6.283) * px(24);
          const driftY = seconds * px(3.2) * (0.35 + seeded(index + 301) * 0.5);
          const y = ((baseY + driftY) % (height + px(40))) - px(20);
          const size = px(1.4 + seeded(index + 401) * 1.5);

          const ignition = ignitions.find((i) => i.particle === index);
          const lit = ignition
            ? Math.max(0, Math.min(1, (clock - ignition.frame) / 10))
            : 0;
          // Once lit, a point stays lit — the cold open is a sequence of small
          // decisions, not a flicker.
          const fill = lit > 0 ? litColor : alpha.particle;

          return (
            <circle
              key={index}
              cx={baseX + driftX}
              cy={y}
              r={lit > 0 ? size * (1 + lit * 0.9) : size}
              fill={fill}
              opacity={lit > 0 ? 0.35 + lit * 0.65 : 0.25 + seeded(index + 501) * 0.55}
            />
          );
        })}
      </svg>
    </AbsoluteFill>
  );
};

export const NightGround: React.FC<{children?: React.ReactNode}> = ({children}) => (
  <AbsoluteFill style={{backgroundColor: palette.night}}>{children}</AbsoluteFill>
);

export const PaperGround: React.FC<{children?: React.ReactNode}> = ({children}) => (
  <AbsoluteFill style={{backgroundColor: palette.ivory}}>{children}</AbsoluteFill>
);
