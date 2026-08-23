import React from 'react';
import {AbsoluteFill} from 'remotion';
import {palette} from '../theme';
import {useLayout} from '../layout';
import {Body} from './Headline';
import {PaperGround} from './Ground';
import {Reveal} from './Reveal';
import {StepLabel} from './StepLabel';

/**
 * The frame every product shot shares: paper ground, the step on the left, the
 * product on the right — and on the narrow cuts, the step above the product
 * instead. One idea per shot, so the step copy is settled before the product
 * starts moving.
 */
export const ProductScene: React.FC<{
  step: string;
  title: string;
  body: string;
  headingDelay: number;
  children: React.ReactNode;
}> = ({step, title, body, headingDelay, children}) => {
  const {px, gutter, stacked, shape} = useLayout();

  return (
    <AbsoluteFill>
      <PaperGround />
      <AbsoluteFill
        style={{
          padding: gutter,
          display: 'flex',
          flexDirection: stacked ? 'column' : 'row',
          alignItems: stacked ? 'stretch' : 'center',
          justifyContent: stacked ? 'center' : 'space-between',
          gap: px(stacked ? 56 : 96),
        }}
      >
        <div
          style={{
            flex: stacked ? '0 0 auto' : '1 1 0',
            maxWidth: stacked ? '100%' : px(560),
          }}
        >
          <Reveal delay={headingDelay} distance={10}>
            <StepLabel step={step} title={title} size={shape === 'horizontal' ? 54 : 62} />
          </Reveal>
          <Reveal delay={headingDelay + 2} distance={10}>
            <Body
              size={shape === 'horizontal' ? 22 : 26}
              color={palette.muted}
              style={{marginTop: px(22)}}
            >
              {body}
            </Body>
          </Reveal>
        </div>

        <div
          style={{
            flex: stacked ? '0 0 auto' : '0 0 auto',
            display: 'flex',
            justifyContent: 'center',
          }}
        >
          {children}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
