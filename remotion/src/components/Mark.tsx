import React from 'react';
import {interpolate} from 'remotion';
import {palette} from '../theme';

/**
 * The Personal mark: four petals cut from a disc, leaving a four-pointed star in
 * the negative space. One petal path, rotated 0/90/180/270, so the mark is
 * exactly symmetrical rather than carrying the inconsistencies of a trace.
 */

const PETAL =
  'M148.59 0 L153.99 .08 C153.58 33.87 138.27 69.79 116.62 95.42 C89.09 128.17 49.62 148.57 6.98 152.09 L0 152.59 C5.22 67.22 61.74 5.05 148.59 0 Z';

const ROTATIONS = [0, 90, 180, 270] as const;

export const Mark: React.FC<{
  size: number;
  color?: string;
  /**
   * 0 → nothing, 1 → whole mark. The petals rotate into place one after another
   * rather than all four fading up together; this is the only thing in the film
   * that rotates, and it stops the moment it arrives.
   */
  progress?: number;
}> = ({size, color = palette.signature, progress = 1}) => {
  return (
    <svg
      width={size}
      height={size}
      viewBox="-163.5 -163.5 327 327"
      aria-hidden
      focusable="false"
    >
      <g fill={color}>
        {ROTATIONS.map((angle, index) => {
          // Each petal owns a quarter of the progress, with a little overlap so
          // the four arrivals read as one gesture.
          const start = index * 0.2;
          const local = interpolate(progress, [start, start + 0.4], [0, 1], {
            extrapolateLeft: 'clamp',
            extrapolateRight: 'clamp',
          });
          const swing = interpolate(local, [0, 1], [-22, 0]);

          return (
            <path
              key={angle}
              d={PETAL}
              opacity={local}
              transform={`rotate(${angle + swing}) translate(9.2 9.2) scale(${interpolate(
                local,
                [0, 1],
                [0.86, 1],
              )})`}
            />
          );
        })}
      </g>
    </svg>
  );
};
