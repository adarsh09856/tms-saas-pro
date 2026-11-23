import React from 'react';
import Svg, { Circle, Path } from 'react-native-svg';

type KodewavesRouteMarkProps = {
    size?: number;
    strokeColor?: string;
};

const KodewavesRouteMark = ({ size = 56, strokeColor = '#294b3a' }: KodewavesRouteMarkProps) => (
    <Svg width={size} height={size} viewBox='0 0 112 112' accessible={false}>
        <Path d='M31 35v42M31 56h15c19 0 16-21 35-21M46 56c19 0 16 21 35 21' fill='none' stroke={strokeColor} strokeWidth={7} strokeLinecap='round' strokeLinejoin='round' />
        <Circle cx={31} cy={35} r={5} fill='#b4873a' />
        <Circle cx={81} cy={35} r={5} fill='#93b79a' />
        <Circle cx={81} cy={77} r={5} fill='#b4873a' />
    </Svg>
);

export default KodewavesRouteMark;
