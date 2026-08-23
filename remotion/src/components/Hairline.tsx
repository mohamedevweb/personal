import React from 'react';
import {palette} from '../theme';
import {useLayout} from '../layout';

/**
 * A single rule. `progress` draws it from its origin rather than fading it in,
 * because a line that fades reads as a shadow and a line that draws reads as a
 * decision.
 */
export const Hairline: React.FC<{
  width?: number | string;
  height?: number | string;
  color?: string;
  progress?: number;
  vertical?: boolean;
  style?: React.CSSProperties;
}> = ({width, height, color = palette.line, progress = 1, vertical = false, style}) => {
  const {px} = useLayout();
  const thickness = Math.max(1, px(1));

  return (
    <div
      style={{
        width: vertical ? thickness : (width ?? '100%'),
        height: vertical ? (height ?? '100%') : thickness,
        backgroundColor: color,
        transform: vertical ? `scaleY(${progress})` : `scaleX(${progress})`,
        transformOrigin: vertical ? 'top center' : 'left center',
        ...style,
      }}
    />
  );
};
