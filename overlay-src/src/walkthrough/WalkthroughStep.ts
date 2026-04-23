export interface WalkthroughStep {
  targetSelector: string;
  title: string;
  body: string;
  placement: 'top' | 'bottom' | 'left' | 'right' | 'auto';
}

export const walkthroughSteps: WalkthroughStep[] = [
  {
    targetSelector: '.noted-toolbar',
    title: 'This is your toolbar',
    body: 'The toolbar is your command center. From here you can toggle annotation mode, filter feedback by status, and access settings. It stays fixed at the top of the page while you scroll.',
    placement: 'auto',
  },
  {
    targetSelector: '.noted-tool-btn[data-tip*="Pin"]',
    title: 'Drop a pin',
    body: 'Click this to enter annotation mode. Then click anywhere on the page to place a pin and leave feedback. Your client sees exactly what you see, right where you see it.',
    placement: 'auto',
  },
  {
    targetSelector: '.noted-panel',
    title: 'Your feedback panel',
    body: 'All annotations for the current page live here. You can filter by status, reply to threads, and resolve items when they are addressed. Think of it as your project\u2019s living punch list.',
    placement: 'left',
  },
  {
    targetSelector: '.noted-panel-tabs',
    title: 'Track progress with statuses',
    body: 'Every annotation starts as Open. Move items to In Review when work begins, and Resolved when complete. Use the filters to focus on what still needs attention.',
    placement: 'left',
  },
  {
    targetSelector: '.noted-widget-page',
    title: 'Switch between pages',
    body: 'Noted Visual Feedback tracks feedback per page. Use this selector to jump between pages on the site without leaving the overlay. All pins stay anchored to the page they were placed on.',
    placement: 'auto',
  },
  {
    targetSelector: '.noted-widget-btn[data-tip="Dashboard"]',
    title: 'Settings and more',
    body: 'Access notification preferences, export feedback, and manage your team from here. You can also replay this walkthrough any time from the settings page.',
    placement: 'auto',
  },
  {
    targetSelector: '',
    title: 'Tips for getting the most out of Noted Visual Feedback',
    body: 'Be specific: reference exact elements when leaving feedback. Use statuses consistently so nothing falls through the cracks. Resolve items promptly to keep the list clean. And remember, your feedback data never leaves your WordPress database.',
    placement: 'auto',
  },
];
