export interface StoryLabels {
  back: string;
  published: string;
  breaking: string;
}

const EN: StoryLabels = {
  back: "Back to wire feed",
  published: "Published",
  breaking: "BREAKING",
};

const BN: StoryLabels = {
  back: "ওয়্যার ফিডে ফিরে যান",
  published: "প্রকাশিত",
  breaking: "ব্রেকিং",
};

export function storyLabels(language?: string): StoryLabels {
  return language === "bn" ? BN : EN;
}
