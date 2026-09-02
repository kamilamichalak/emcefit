import {
    Activity,
    Bike,
    Dumbbell,
    Flame,
    Footprints,
    HeartPulse,
    Music,
    Music2,
    Music4,
    PersonStanding,
    Repeat,
    Sparkles,
    Target,
    Timer,
    Waves,
    Zap,
} from 'lucide-vue-next';

// Musi być zgodne z App\Domain\Scheduling\Models\ClassType::ICONS (kolejność = kolejność w pickerze).
export const CLASS_TYPE_ICONS = {
    Dumbbell,
    Activity,
    HeartPulse,
    Flame,
    Zap,
    Timer,
    Music,
    Music2,
    Music4,
    Footprints,
    PersonStanding,
    Bike,
    Waves,
    Target,
    Sparkles,
    Repeat,
};

export const CLASS_TYPE_ICON_NAMES = Object.keys(CLASS_TYPE_ICONS);

export const DEFAULT_CLASS_TYPE_ICON = 'Dumbbell';

export const iconComponent = (name) =>
    CLASS_TYPE_ICONS[name] ?? CLASS_TYPE_ICONS[DEFAULT_CLASS_TYPE_ICON];
