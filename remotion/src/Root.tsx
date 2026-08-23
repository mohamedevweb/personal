import React from 'react';
import {Composition} from 'remotion';

import {
  LaunchFilm,
  defaultLaunchFilmProps,
  launchFilmSchema,
} from './LaunchFilm';
import {DURATION_IN_FRAMES, FPS} from './timing';

/**
 * One film, three shapes. The compositions differ only in their dimensions —
 * the scene components are shared, and each one reads what shape it is in from
 * the `<Layout>` context rather than from a fork of this file.
 */
export const RemotionRoot: React.FC = () => {
  const shared = {
    component: LaunchFilm,
    durationInFrames: DURATION_IN_FRAMES,
    fps: FPS,
    schema: launchFilmSchema,
    defaultProps: defaultLaunchFilmProps,
  } as const;

  return (
    <>
      <Composition id="LaunchFilm" {...shared} width={1920} height={1080} />
      <Composition id="LaunchFilmVertical" {...shared} width={1080} height={1920} />
      <Composition id="LaunchFilmSquare" {...shared} width={1080} height={1080} />
    </>
  );
};
