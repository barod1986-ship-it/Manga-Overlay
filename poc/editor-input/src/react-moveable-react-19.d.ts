import type { JSX as ReactJSX } from 'react';

// react-moveable 0.56 still names the pre-React-19 global JSX namespace
// in three public declarations. Keep library checking enabled and bridge only
// the missing return type until the package publishes React.JSX declarations.
declare global {
  namespace JSX {
    type Element = ReactJSX.Element;
  }
}

export {};
