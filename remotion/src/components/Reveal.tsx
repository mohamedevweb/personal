import React from 'react';
import {useCurrentFrame, useVideoConfig} from 'remotion';
import {enter} from '../motion';
import {useLayout} from '../layout';

/**
 * The stagger wrapper. Everything that enters the film enters through here:
 * opacity 0→1 and a short travel on the house spring, offset by `delay`.
 *
 * It takes a frame rather than reading one when `frame` is passed, so a parent
 * can drive a whole group off a single clock.
 */
export const Reveal: React.FC<{
  children: React.ReactNode;
  delay?: number;
  /** Travel distance in design px. Negative moves down into place. */
  distance?: number;
  axis?: 'y' | 'x';
  frame?: number;
  style?: React.CSSProperties;
}> = ({children, delay = 0, distance = 10, axis = 'y', frame, style}) => {
  const currentFrame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const {px} = useLayout();

  const progress = enter({frame: frame ?? currentFrame, fps, delay});
  const offset = px(distance) * (1 - progress);

  return (
    <div
      style={{
        opacity: progress,
        transform: axis === 'y' ? `translateY(${offset}px)` : `translateX(${offset}px)`,
        willChange: 'transform, opacity',
        ...style,
      }}
    >
      {children}
    </div>
  );
};

/**
 * Reveals its children in sequence on a fixed stagger. Used wherever a group of
 * lines lands as one gesture — 60ms is two frames at 30fps.
 */
export const RevealGroup: React.FC<{
  children: React.ReactNode;
  delay?: number;
  stagger?: number;
  distance?: number;
  style?: React.CSSProperties;
  itemStyle?: React.CSSProperties;
}> = ({children, delay = 0, stagger = 2, distance = 10, style, itemStyle}) => {
  return (
    <div style={style}>
      {React.Children.map(children, (child, index) => (
        <Reveal delay={delay + index * stagger} distance={distance} style={itemStyle}>
          {child}
        </Reveal>
      ))}
    </div>
  );
};
