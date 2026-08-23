import React from 'react';
import {palette} from '../theme';

/**
 * The Instagram mark, redrawn as a hairline glyph so it sits at the same weight
 * as the rest of the card rather than importing another brand's colour into the
 * frame. It is furniture: it says which account, and nothing more.
 */
export const InstagramGlyph: React.FC<{size: number; color?: string}> = ({
  size,
  color = palette.ink,
}) => {
  const stroke = (size / 24) * 1.6;

  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden focusable="false">
      <rect x="2.6" y="2.6" width="18.8" height="18.8" rx="5.6" stroke={color} strokeWidth={stroke} />
      <circle cx="12" cy="12" r="4.5" stroke={color} strokeWidth={stroke} />
      <circle cx="17.3" cy="6.7" r={stroke * 0.75} fill={color} />
    </svg>
  );
};
