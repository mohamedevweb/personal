import React from 'react';
import {families} from '../fonts';
import {palette, type as typeScale} from '../theme';
import {useLayout} from '../layout';

/** Display type. Always Instrument Serif, always tight. */
export const Headline: React.FC<{
  children: React.ReactNode;
  size: number;
  color?: string;
  italic?: boolean;
  style?: React.CSSProperties;
}> = ({children, size, color = palette.ink, italic = false, style}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        fontFamily: families.display,
        fontWeight: 400,
        fontStyle: italic ? 'italic' : 'normal',
        fontSize: px(size),
        letterSpacing: typeScale.displayTracking,
        lineHeight: typeScale.displayLeading,
        color,
        ...style,
      }}
    >
      {children}
    </div>
  );
};

/** Body copy. Inter, generous leading, never the display face. */
export const Body: React.FC<{
  children: React.ReactNode;
  size?: number;
  color?: string;
  weight?: 400 | 500 | 600;
  style?: React.CSSProperties;
}> = ({children, size = 22, color = palette.muted, weight = 400, style}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        fontFamily: families.body,
        fontWeight: weight,
        fontSize: px(size),
        lineHeight: typeScale.bodyLeading,
        color,
        ...style,
      }}
    >
      {children}
    </div>
  );
};
