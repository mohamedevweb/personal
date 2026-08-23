import React, {createContext, useContext, useMemo} from 'react';
import {useVideoConfig} from 'remotion';

/**
 * The film is drawn once and shown in three shapes. Nothing is forked: scenes
 * ask this context how much room they have and whether their two halves sit
 * side by side or stacked, and lay themselves out from the answer.
 *
 * `unit` is the single scale factor. Every size in a scene is written as a
 * multiple of it, so the horizontal cut is the design and the other two are the
 * same design at a different size.
 */

export type Shape = 'horizontal' | 'square' | 'vertical';

export type LayoutValue = {
  shape: Shape;
  /** 1 on the 1920×1080 cut. Multiply every px value by this. */
  unit: number;
  /** True when a scene's two halves should stack instead of sitting side by side. */
  stacked: boolean;
  /** Outer margin of the safe area, already scaled. */
  gutter: number;
  /** Width available to content inside the gutters. */
  contentWidth: number;
  contentHeight: number;
  /** Width a product card should take. */
  cardWidth: number;
  /** Convenience: scale a value designed on the horizontal cut. */
  px: (value: number) => number;
};

const LayoutContext = createContext<LayoutValue | null>(null);

const shapeOf = (width: number, height: number): Shape => {
  const ratio = width / height;
  if (ratio > 1.2) {
    return 'horizontal';
  }
  if (ratio < 0.85) {
    return 'vertical';
  }
  return 'square';
};

export const Layout: React.FC<{children: React.ReactNode}> = ({children}) => {
  const {width, height} = useVideoConfig();

  const value = useMemo<LayoutValue>(() => {
    const shape = shapeOf(width, height);

    // The horizontal cut is the reference. The square and vertical cuts are
    // narrower, so they scale off width rather than off the diagonal — type
    // stays the same optical size relative to the frame edge it reads against.
    const unit =
      shape === 'horizontal' ? width / 1920 : Math.min(width / 1080, height / 1350);

    const px = (v: number) => v * unit;
    const gutter = px(shape === 'horizontal' ? 130 : 88);
    const contentWidth = width - gutter * 2;
    const contentHeight = height - gutter * 2;
    const stacked = shape !== 'horizontal';

    return {
      shape,
      unit,
      stacked,
      gutter,
      contentWidth,
      contentHeight,
      cardWidth: stacked ? contentWidth : contentWidth * 0.52,
      px,
    };
  }, [width, height]);

  return <LayoutContext.Provider value={value}>{children}</LayoutContext.Provider>;
};

export const useLayout = (): LayoutValue => {
  const value = useContext(LayoutContext);
  if (!value) {
    throw new Error('useLayout must be used inside <Layout>');
  }
  return value;
};
