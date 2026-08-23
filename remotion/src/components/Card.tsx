import React from 'react';
import {alpha, palette, radius} from '../theme';
import {useLayout} from '../layout';

/**
 * A product surface on paper: --surface on --ivory, a 1px --line, radius 14, and
 * a shadow that is barely there. The card is furniture; it should never be the
 * thing you notice in a frame.
 */
export const Card: React.FC<{
  children: React.ReactNode;
  width?: number | string;
  padding?: number;
  lifted?: boolean;
  style?: React.CSSProperties;
}> = ({children, width, padding = 34, lifted = false, style}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        width: width ?? '100%',
        backgroundColor: palette.surface,
        border: `${Math.max(1, px(1))}px solid ${palette.line}`,
        borderRadius: px(radius.card),
        padding: px(padding),
        boxShadow: lifted
          ? `0 ${px(18)}px ${px(44)}px ${alpha.cardShadowLift}`
          : `0 ${px(6)}px ${px(18)}px ${alpha.cardShadow}`,
        boxSizing: 'border-box',
        ...style,
      }}
    >
      {children}
    </div>
  );
};

/** The header strip of a card: a label, and optionally a live indicator. */
export const CardHeader: React.FC<{
  children: React.ReactNode;
  live?: boolean;
  liveOpacity?: number;
  style?: React.CSSProperties;
}> = ({children, live = false, liveOpacity = 1, style}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: px(10),
        ...style,
      }}
    >
      {live ? (
        <div
          style={{
            width: px(8),
            height: px(8),
            borderRadius: '50%',
            backgroundColor: palette.signature,
            opacity: liveOpacity,
          }}
        />
      ) : null}
      {children}
    </div>
  );
};
