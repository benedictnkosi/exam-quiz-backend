import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Learner } from '../entities/learner.entity';
import { SubjectPerformanceService } from './subjectPerformance.service';
import { OpenAI } from 'openai';

@Injectable()
export class CareerAdviceService {
    private openai: OpenAI;

    constructor(
        @InjectRepository(Learner)
        private learnerRepository: Repository<Learner>,
        private subjectPerformanceService: SubjectPerformanceService,
    ) {
        this.openai = new OpenAI({
            apiKey: process.env.OPENAI_API_KEY,
        });
    }

    async generateCareerAdvice(learnerId: string): Promise<string> {
        const learner = await this.learnerRepository.findOne({
            where: { id: learnerId },
        });

        if (!learner) {
            throw new Error('Learner not found');
        }

        // Get subject performance data
        const subjectPerformance = await this.subjectPerformanceService.getSubjectPerformance(learnerId);

        // Filter subjects with at least 25 answers
        const filteredSubjects = subjectPerformance.filter(subject => subject.totalQuestions >= 25);

        if (filteredSubjects.length === 0) {
            return 'Not enough data to generate career advice. Please answer more questions to get personalized recommendations.';
        }

        // Prepare the prompt for OpenAI
        const prompt = this.buildPrompt(filteredSubjects);

        try {
            const completion = await this.openai.chat.completions.create({
                model: 'gpt-3.5-turbo',
                messages: [
                    {
                        role: 'system',
                        content: 'You are a career guidance counselor helping students choose their career paths based on their academic performance.',
                    },
                    {
                        role: 'user',
                        content: prompt,
                    },
                ],
                temperature: 0.7,
                max_tokens: 500,
            });

            const advice = completion.choices[0].message.content;

            // Update learner with new advice
            await this.learnerRepository.update(learnerId, {
                careerAdvice: advice,
                careerAdviceLastUpdated: new Date(),
            });

            return advice;
        } catch (error) {
            console.error('Error generating career advice:', error);
            throw new Error('Failed to generate career advice');
        }
    }

    private buildPrompt(subjects: any[]): string {
        const subjectDetails = subjects
            .map(
                (subject) =>
                    `${subject.subjectName}: ${subject.percentageScore}% correct (${subject.correctAnswers} out of ${subject.totalQuestions} questions)`,
            )
            .join('\n');

        return `Based on the following academic performance, provide career advice and suggest suitable career paths. Consider the student's strengths and areas for improvement:

${subjectDetails}

Please provide a response using emojis to organize and highlight key points:

🎯 A brief analysis of the student's academic strengths
💼 3-5 suitable career paths that align with their performance
📚 Specific recommendations for further development
⚠️ Any potential challenges to be aware of

Use relevant emojis throughout the response to make it engaging and easy to read. For example:
- Use 🧠 for analytical points
- Use 💡 for insights
- Use 🚀 for growth opportunities
- Use ⭐ for standout achievements
- Use 🔍 for areas to focus on

Format the response in a clear, structured manner with appropriate emojis for each section and key points.`;
    }
} 