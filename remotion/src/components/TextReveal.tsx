import React from 'react';
import {interpolate} from 'remotion';

/**
 * A fast character reveal — the fiction is that something is being written or
 * read, so the string arrives left to right. No cursor, no per-letter easing:
 * the characters are simply already there, one after another, quickly.
 *
 * Used only where that fiction holds. Text never enters letter by letter as
 * decoration.
 */
export const TextReveal: React.FC<{
  text: string;
  /** 0 → nothing, 1 → the whole string. */
  progress: number;
  style?: React.CSSProperties;
}> = ({text, progress, style}) => {
  const shown = Math.round(
    interpolate(progress, [0, 1], [0, text.length], {
      extrapolateLeft: 'clamp',
      extrapolateRight: 'clamp',
    }),
  );

  return (
    <span style={style}>
      {text.slice(0, shown)}
      {/* Holds the line's width so nothing reflows as it fills. */}
      <span style={{opacity: 0}}>{text.slice(shown)}</span>
    </span>
  );
};
