import Attempt from './Attempt';

const AttemptList = ({ attempts, onEditTrack, onAddTrack, onDeleteTrack, onEditAttempt, onSaveAttempt }) => {
    return (
        <div className="row row-deck row-cards">
            <div className="col-12">
                {attempts.map((attempt, index) => (
                    <Attempt
                        key={attempt.id || index}
                        attempt={attempt}
                        isActive={index === attempts.length - 1}
                        onEditTrack={onEditTrack}
                        onAddTrack={onAddTrack}
                        onDeleteTrack={onDeleteTrack}
                        onEditAttempt={onEditAttempt}
                        onSaveAttempt={onSaveAttempt}
                    />
                ))}
            </div>
        </div>
    );
};

export default AttemptList;
