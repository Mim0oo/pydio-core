import withContextMenu from './context-menu'
import {withMenu, withControls} from './controls'
import withErrors from './errors'
import withLoader from './loader'
import {selectionControls, withSelection} from './selection'
import {ContainerSizeProvider, ImageSizeProvider, withResize} from './size'
import {withResolution} from './resolution'
import {URLProvider} from './urls'
import PaletteModifier from './PaletteModifier'
import * as Animations from "./animations";

const PydioHOCs = {
    selectionControls,
    withControls,
    withContextMenu,
    withErrors,
    withLoader,
    withMenu,
    withResize,
    withResolution,
    withSelection,
    Animations,
    PaletteModifier,
    URLProvider,
    ContainerSizeProvider,
    ImageSizeProvider
};

export {PydioHOCs as default}
