export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type Generation = {
    id: string;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    status: GenerationStatus;
    estimated_price_nanos: number;
    max_price_nanos: number;
    price_nanos: number | null;
    tokens: {
        input_text: number;
        input_image: number;
        output: number;
        reasoning: number;
    } | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
};
