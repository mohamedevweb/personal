import React from 'react';
import {families} from '../fonts';
import {palette, type as typeScale} from '../theme';
import {useLayout} from '../layout';

/** Small, tracked-out label type. Never larger than the thing it introduces. */
export const Eyebrow: React.FC<{
  children: React.ReactNode;
  color?: string;
  size?: number;
  style?: React.CSSProperties;
}> = ({children, color = palette.muted, size = 15, style}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        fontFamily: families.body,
        fontWeight: 500,
        fontSize: px(size),
        letterSpacing: typeScale.eyebrowTracking,
        textTransform: 'uppercase',
        color,
        ...style,
      }}
    >
      {children}
    </div>
  );
};
