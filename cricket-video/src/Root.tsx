import React from 'react';
import { Composition } from 'remotion';
import { ResultVideo, Match } from './ResultVideo';
import match from '../match.json';

const FPS = 30;

export const Root: React.FC = () => (
	<Composition
		id="ResultVideo"
		component={ResultVideo}
		width={1080}
		height={1920}
		fps={FPS}
		durationInFrames={Math.round((match as Match).scenes.end * FPS)}
		defaultProps={match as Match}
		calculateMetadata={({ props }) => ({ durationInFrames: Math.round(props.scenes.end * FPS) })}
	/>
);
