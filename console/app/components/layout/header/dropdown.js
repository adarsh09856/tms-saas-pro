import HeaderDropdownComponent from '@fleetbase/ember-ui/components/layout/header/dropdown';

/**
 * Header menus must render through ember-basic-dropdown's wormhole by default.
 * Keeping profile and company menus inside the 57px header traps them in its
 * stacking and clipping context. Callers can still opt into in-place rendering.
 */
export default class ConsoleHeaderDropdownComponent extends HeaderDropdownComponent {}
