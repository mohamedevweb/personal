import React from 'react';
import {families} from '../fonts';
import {palette, type as typeScale} from '../theme';
import {useLayout} from '../layout';
import {Headline} from './Headline';

/**
 * `01 Understand` — the section number set small in Inter against the step name
 * in the display face. The number is muted; the word is the thing.
 */
export const StepLabel: React.FC<{
  step: string;
  title: string;
  size?: number;
}> = ({step, title, size = 54}) => {
  const {px} = useLayout();

  return (
    <div style={{display: 'flex', alignItems: 'baseline', gap: px(18)}}>
      <span
        style={{
          fontFamily: families.body,
          fontWeight: 500,
          fontSize: px(size * 0.3),
          letterSpacing: typeScale.eyebrowTracking,
          color: palette.muted,
        }}
      >
        {step}
      </span>
      <Headline size={size} color={palette.ink}>
        {title}
      </Headline>
    </div>
  );
};
